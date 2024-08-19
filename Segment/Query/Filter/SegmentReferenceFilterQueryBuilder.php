<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Segment\Query\Filter;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\ORM\EntityManager;
use Mautic\LeadBundle\Event\SegmentDictionaryGenerationEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\ContactSegmentFilterFactory;
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
        EventDispatcherInterface $dispatcher
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
