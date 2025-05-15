<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Segment\Query\Filter;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\ORM\EntityManager;
use Mautic\LeadBundle\Event\SegmentDictionaryGenerationEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\ContactSegmentFilterFactory;
use Mautic\LeadBundle\Segment\OperatorOptions;
use Mautic\LeadBundle\Segment\Query\Filter\BaseFilterQueryBuilder;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use Mautic\LeadBundle\Segment\RandomParameterName;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\DTO\CompanySegmentAsLeadSegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Exception\SegmentNotFoundException;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Exception\SegmentQueryException;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Segment\Query\CompanySegmentQueryBuilder;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Segment\Query\QueryException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SegmentReferenceFilterQueryBuilder extends BaseFilterQueryBuilder implements EventSubscriberInterface
{
    public function __construct(
        RandomParameterName $randomParameterNameService,
        private CompanySegmentQueryBuilder $companySegmentQueryBuilder,
        private EntityManager $entityManager,
        private ContactSegmentFilterFactory $leadSegmentFilterFactory,
        EventDispatcherInterface $dispatcher,
    ) {
        parent::__construct($randomParameterNameService, $dispatcher);
    }

    public static function getServiceId(): string
    {
        return self::class;
    }

    /**
     * @see \Mautic\LeadBundle\Segment\Query\Filter\SegmentReferenceFilterQueryBuilder::applyQuery
     *
     * @throws SegmentNotFoundException
     * @throws SegmentQueryException
     * @throws \Doctrine\DBAL\Exception
     * @throws QueryException
     */
    public function applyQuery(QueryBuilder $queryBuilder, ContactSegmentFilter $filter): QueryBuilder
    {
        if (CompanySegmentModel::PROPERTIES_FIELD !== $filter->getField()) {
            throw new \RuntimeException('The supported field is '.CompanySegmentModel::PROPERTIES_FIELD);
        }

        $from = $queryBuilder->getQueryPart('from');
        assert(is_array($from));
        if (
            array_key_exists(0, $from)
            && array_key_exists('table', $from[0])
            && $from[0]['table'] === MAUTIC_TABLE_PREFIX.'leads'
        ) {
            return $this->applyQueryToLeadSegment($queryBuilder, $filter);
        }

        return $this->applyQueryToCompanySegment($queryBuilder, $filter);
    }

    private function applyQueryToCompanySegment(QueryBuilder $queryBuilder, ContactSegmentFilter $filter): QueryBuilder
    {
        $companiesTableAlias = $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'companies');
        \assert(is_string($companiesTableAlias));
        $segmentIds = $filter->getParameterValue();

        \assert(is_array($segmentIds) || is_numeric($segmentIds));

        if (!is_array($segmentIds)) {
            $segmentIds = [(int) $segmentIds];
        }

        $orLogic = [];

        foreach ($segmentIds as $segmentId) {
            $exclusion = in_array($filter->getOperator(), ['notExists', 'notIn'], true);

            /** @var CompanySegment|null $companySegment */
            $companySegment = $this->entityManager->getRepository(CompanySegment::class)->find($segmentId);
            if (null === $companySegment) {
                throw new SegmentNotFoundException(sprintf('Segment %d used in the filter does not exist anymore.', $segmentId));
            }

            $contactSegment = new CompanySegmentAsLeadSegment($companySegment);
            $filters        = $this->leadSegmentFilterFactory->getSegmentFilters($contactSegment);

            $segmentQueryBuilder           = $this->companySegmentQueryBuilder->assembleCompaniesSegmentQueryBuilder($companySegment, $filters, true);
            $subSegmentCompaniesTableAlias = $segmentQueryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'companies');
            \assert(is_string($subSegmentCompaniesTableAlias));
            $segmentQueryBuilder->resetQueryParts(['select'])->select('null');

            // If the segment contains no filters; it means its for manually subscribed only
            if (count($filters) > 0) {
                $segmentQueryBuilder = $this->companySegmentQueryBuilder->addManuallyUnsubscribedQuery($segmentQueryBuilder, $companySegment);
            }

            $segmentQueryBuilder = $this->companySegmentQueryBuilder->addManuallySubscribedQuery($segmentQueryBuilder, $companySegment);

            // This query looks a bit too complex, but if the segment(s) has more or less complex filter this is (probably)
            // the way to go. Hours spent optimizing: 3. Increment if you spent yet more here.
            $segmentQueryBuilder = $this->companySegmentQueryBuilder->addCompanySegmentQuery($segmentQueryBuilder, $companySegment);

            $parameters = $segmentQueryBuilder->getParameters();
            foreach ($parameters as $key => $value) {
                $queryBuilder->setParameter($key, $value);
            }

            $this->companySegmentQueryBuilder->queryBuilderGenerated($companySegment, $segmentQueryBuilder);

            $segmentQueryWherePart = $segmentQueryBuilder->getQueryPart('where');
            $segmentQueryBuilder->where(sprintf('%s.id = %s.id', $companiesTableAlias, $subSegmentCompaniesTableAlias));
            $segmentQueryBuilder->andWhere($segmentQueryWherePart);

            if ($exclusion) {
                $expression = $queryBuilder->expr()->notExists($segmentQueryBuilder->getSQL());
            } else {
                $expression = $queryBuilder->expr()->exists($segmentQueryBuilder->getSQL());
            }

            if (!$exclusion && count($segmentIds) > 1) {
                $orLogic[] = $expression;
            } else {
                $queryBuilder->addLogic($expression, $filter->getGlue());
            }

            // Preserve memory and detach segments that are not needed anymore.
            $this->entityManager->detach($companySegment);
        }

        if (count($orLogic) > 0) {
            $queryBuilder->addLogic(new CompositeExpression(CompositeExpression::TYPE_OR, $orLogic), $filter->getGlue());
        }

        return $queryBuilder;
    }

    private function applyQueryToLeadSegment(QueryBuilder $queryBuilder, ContactSegmentFilter $filter): QueryBuilder
    {
        $leadAlias               = $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'leads');
        $companiesLeadTableAlias = $this->generateRandomParameterName();
        assert(is_string($leadAlias));
        $queryBuilder->join(
            $leadAlias,
            MAUTIC_TABLE_PREFIX.'companies_leads',
            $companiesLeadTableAlias,
            $companiesLeadTableAlias.'.lead_id = '.$leadAlias.'.id AND '.$companiesLeadTableAlias.'.is_primary = 1'
        );

        $segmentIds = $filter->getParameterValue();
        if (OperatorOptions::EMPTY === $filter->getOperator() || 'notEmpty' === $filter->getOperator()) {
            $segmentIds = $this->entityManager->getRepository(CompanySegment::class)->findAll();
            $segmentIds = array_map(static fn (CompanySegment $segment): ?int => $segment->getId(), $segmentIds);
        }

        \assert(is_array($segmentIds) || is_numeric($segmentIds));

        if (!is_array($segmentIds)) {
            $segmentIds = [(int) $segmentIds];
        }

        $orLogic           = [];
        foreach ($segmentIds as $segmentId) {
            $exclusion = in_array($filter->getOperator(), ['notExists', 'notIn', 'empty'], true);

            /** @var CompanySegment|null $companySegment */
            $companySegment    = $this->entityManager->getRepository(CompanySegment::class)->find($segmentId);

            if (null === $companySegment) {
                throw new SegmentNotFoundException(sprintf('Segment %d used in the filter does not exist anymore.', $segmentId));
            }

            $contactSegment      = new CompanySegmentAsLeadSegment($companySegment);
            $filters             = $this->leadSegmentFilterFactory->getSegmentFilters($contactSegment);
            $segmentQueryBuilder = $this->companySegmentQueryBuilder->assembleCompaniesSegmentQueryBuilderLeadSegment(
                $companySegment,
                $filters,
                true
            );
            $subSegmentCompaniesTableAlias = $segmentQueryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'companies');
            if (false === $subSegmentCompaniesTableAlias) {
                $subSegmentCompaniesTableAlias = $this->generateRandomParameterName();
            }
            \assert(is_string($subSegmentCompaniesTableAlias));
            $segmentQueryBuilder->resetQueryParts(['select'])->select('null');

            // If the segment contains no filters; it means its for manually subscribed only
            if (count($filters) > 0) {
                $segmentQueryBuilder = $this->companySegmentQueryBuilder->addManuallyUnsubscribedQuery($segmentQueryBuilder, $companySegment);
            }

            $segmentQueryBuilder = $this->companySegmentQueryBuilder->addManuallySubscribedQuery($segmentQueryBuilder, $companySegment);
            // This query looks a bit too complex, but if the segment(s) has more or less complex filter this is (probably)
            // the way to go. Hours spent optimizing: 3. Increment if you spent yet more here.
            $segmentQueryBuilder = $this->companySegmentQueryBuilder->addCompanySegmentQuery($segmentQueryBuilder, $companySegment);

            $parameters = $segmentQueryBuilder->getParameters();
            foreach ($parameters as $key => $value) {
                $queryBuilder->setParameter($key, $value);
            }

            $this->companySegmentQueryBuilder->queryBuilderGenerated($companySegment, $segmentQueryBuilder);

            $segmentQueryWherePart = $segmentQueryBuilder->getQueryPart('where');

            $segmentQueryBuilder->where(sprintf('%s.company_id = %s.id', $companiesLeadTableAlias, $subSegmentCompaniesTableAlias));
            $segmentQueryBuilder->andWhere($segmentQueryWherePart);
            if ($exclusion) {
                $expression = $queryBuilder->expr()->notExists($segmentQueryBuilder->getSQL());
            } else {
                $expression = $queryBuilder->expr()->exists($segmentQueryBuilder->getSQL());
            }

            if (!$exclusion && count($segmentIds) > 1) {
                $orLogic[] = $expression;
            } else {
                $queryBuilder->addLogic($expression, $filter->getGlue());
            }
            // Preserve memory and detach segments that are not needed anymore.
            $this->entityManager->detach($companySegment);
        }

        if (count($orLogic) > 0) {
            $queryBuilder->addLogic(new CompositeExpression(CompositeExpression::TYPE_OR, $orLogic), $filter->getGlue());
        }

        return $queryBuilder;
    }

    public function onAddFilter(SegmentDictionaryGenerationEvent $event): void
    {
        $event->addTranslation(CompanySegmentModel::PROPERTIES_FIELD, [
            'type' => self::getServiceId(),
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::SEGMENT_DICTIONARY_ON_GENERATE => 'onAddFilter',
        ];
    }
}
