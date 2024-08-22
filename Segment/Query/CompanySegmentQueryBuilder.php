<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Segment\Query;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManager;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\ContactSegmentFilters;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use Mautic\LeadBundle\Segment\RandomParameterName;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentFilteringEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentQueryBuilderGeneratedEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Exception\SegmentQueryException;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CompanySegmentQueryBuilder
{
    use CompanyBatchLimiterTrait;

    /**
     * @var array<int, array<int, string|int>> Contains segment edges mapping
     */
    private array $dependencyMap = [];

    public function __construct(
        private EntityManager $entityManager,
        private CompanyRepository $companyRepository,
        private CompanySegmentRepository $companySegmentRepository,
        private RandomParameterName $randomParameterName,
        private EventDispatcherInterface $dispatcher
    ) {
    }

    /**
     * @throws SegmentQueryException
     */
    public function assembleCompaniesSegmentQueryBuilder(CompanySegment $companySegment, ContactSegmentFilters $segmentFilters, bool $changeAlias = false): QueryBuilder
    {
        $connection = $this->entityManager->getConnection();
        if ($connection instanceof PrimaryReadReplicaConnection) {
            // Prefer a replica connection if available.
            $connection->ensureConnectedToReplica();
        }

        $queryBuilder = new QueryBuilder($connection);

        $companyTableAlias = $changeAlias ? $this->generateRandomParameterName() : $this->companyRepository->getTableAlias();

        $queryBuilder->select($companyTableAlias.'.id')->from(MAUTIC_TABLE_PREFIX.'companies', $companyTableAlias);

        /*
         * Validate the plan, check for circular dependencies.
         *
         * the bigger count($plan), the higher complexity of query
         */
        $this->getResolutionPlan($companySegment);

        $params     = $queryBuilder->getParameters();
        $paramTypes = $queryBuilder->getParameterTypes();

        /** @var ContactSegmentFilter $filter */
        foreach ($segmentFilters as $filter) {
            if ($this->dispatchPluginFilteringEvent($filter, $queryBuilder)) {
                continue;
            }

            $queryBuilder = $filter->applyQuery($queryBuilder);
            // We need to collect params between union queries in this iteration,
            // because they are overwritten by new union query build
            $params     = array_merge($params, $queryBuilder->getParameters());
            $paramTypes = array_merge($paramTypes, $queryBuilder->getParameterTypes());
        }

        $queryBuilder->setParameters($params, $paramTypes);
        $queryBuilder->applyStackLogic();

        return $queryBuilder;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function wrapInCount(QueryBuilder $qb): QueryBuilder
    {
        $connection = $this->entityManager->getConnection();
        if ($connection instanceof PrimaryReadReplicaConnection) {
            // Prefer a replica connection if available.
            $connection->ensureConnectedToReplica();
        }

        // Add count functions to the query
        $queryBuilder = new QueryBuilder($connection);

        //  If there is any right join in the query we need to select its it
        $primary = $qb->guessPrimaryLeadContactIdColumn();

        if ('orp.lead_id' === $primary) {
            $primary = 'orp.company_id';
        }

        $currentSelects = [];
        $querySelects   = $qb->getQueryParts()['select'];
        \assert(is_array($querySelects));
        foreach ($querySelects as $select) {
            if ($select !== $primary) {
                \assert(is_string($select) || is_array($select));
                $currentSelects[] = $select;
            }
        }

        $qb->select('DISTINCT '.$primary.' as companyIdPrimary');
        foreach ($currentSelects as $select) {
            $qb->addSelect($select);
        }

        $queryBuilder->select('count(companyIdPrimary) count, COALESCE(max(companyIdPrimary), 0) maxId, COALESCE(min(companyIdPrimary), 0) minId')
            ->from('('.$qb->getSQL().')', 'sss');

        $queryBuilder->setParameters($qb->getParameters(), $qb->getParameterTypes());

        return $queryBuilder;
    }

    /**
     * Restrict the query to NEW members of segment.
     *
     * @param array<string, mixed> $batchLimiters
     *
     * @throws QueryException
     */
    public function addNewCompaniesRestrictions(QueryBuilder $queryBuilder, CompanySegment $segment, array $batchLimiters = []): QueryBuilder
    {
        $companiesTableAlias = $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'companies');
        \assert(is_string($companiesTableAlias));
        $expr               = $queryBuilder->expr();
        $tableAlias         = $this->generateRandomParameterName();
        $segmentIdParameter = sprintf(':%ssegmentId', $tableAlias);
        $segmentId          = $segment->getId();

        \assert(null !== $expr);
        \assert(null !== $segmentId);

        $segmentQueryBuilder = $queryBuilder->createQueryBuilder()
            ->select($tableAlias.'.company_id')
            ->from(MAUTIC_TABLE_PREFIX.CompaniesSegments::TABLE_NAME, $tableAlias)
            ->andWhere($expr->eq($tableAlias.'.segment_id', $segmentIdParameter));

        $queryBuilder->setParameter(sprintf('%ssegmentId', $tableAlias), $segmentId);

        $this->addMinMaxLimiters($segmentQueryBuilder, $batchLimiters, CompaniesSegments::TABLE_NAME, 'company_id');

        $queryBuilder->andWhere($expr->notIn($companiesTableAlias.'.id', $segmentQueryBuilder->getSQL()));

        return $queryBuilder;
    }

    public function addCompanySegmentQuery(QueryBuilder $queryBuilder, CompanySegment $companySegment): QueryBuilder
    {
        $companiesTableAlias = $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'companies');
        \assert(is_string($companiesTableAlias));
        $tableAlias = $this->generateRandomParameterName();

        $existsQueryBuilder = $queryBuilder->createQueryBuilder();

        $existsQueryBuilder
            ->select('null')
            ->from(MAUTIC_TABLE_PREFIX.CompaniesSegments::TABLE_NAME, $tableAlias)
            ->andWhere($queryBuilder->expr()->eq($tableAlias.'.segment_id', (int) $companySegment->getId()));

        $existingQueryWherePart = $existsQueryBuilder->getQueryPart('where');
        $existsQueryBuilder->where(sprintf('%s.id = %s.company_id', $companiesTableAlias, $tableAlias));
        $existsQueryBuilder->andWhere($existingQueryWherePart);

        $queryBuilder->andWhere(
            $queryBuilder->expr()->exists($existsQueryBuilder->getSQL())
        );

        return $queryBuilder;
    }

    public function queryBuilderGenerated(CompanySegment $companySegment, QueryBuilder $queryBuilder): void
    {
        if (!$this->dispatcher->hasListeners(CompanySegmentQueryBuilderGeneratedEvent::class)) {
            return;
        }

        $event = new CompanySegmentQueryBuilderGeneratedEvent($companySegment, $queryBuilder);
        $this->dispatcher->dispatch($event);
    }

    public function addManuallySubscribedQuery(QueryBuilder $queryBuilder, CompanySegment $companySegment): QueryBuilder
    {
        \assert(null !== $companySegment->getId());
        $companyTableAlias = $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'companies');

        if (!is_string($companyTableAlias)) {
            throw new \LogicException('The table alias for '.MAUTIC_TABLE_PREFIX.'companies must be a string.');
        }

        $tableAlias = $this->generateRandomParameterName();

        $existsQueryBuilder = $queryBuilder->createQueryBuilder();

        $existsQueryBuilder
            ->select('null')
            ->from(MAUTIC_TABLE_PREFIX.CompaniesSegments::TABLE_NAME, $tableAlias)
            ->andWhere($queryBuilder->expr()->eq($tableAlias.'.segment_id', $companySegment->getId()))
            ->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq($tableAlias.'.manually_added', ':true'),
                    $queryBuilder->expr()->eq($tableAlias.'.manually_removed', ':false')
                )
            );

        $existingQueryWherePart = $existsQueryBuilder->getQueryPart('where');
        $existsQueryBuilder->where(sprintf('%s.id = %s.company_id', $companyTableAlias, $tableAlias));
        $existsQueryBuilder->andWhere($existingQueryWherePart);

        $queryBuilder->orWhere(
            $queryBuilder->expr()->exists($existsQueryBuilder->getSQL())
        )
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('false', false, ParameterType::BOOLEAN);

        return $queryBuilder;
    }

    /**
     * @throws QueryException
     */
    public function addManuallyUnsubscribedQuery(QueryBuilder $queryBuilder, CompanySegment $companySegment): QueryBuilder
    {
        \assert(null !== $companySegment->getId());
        $companyTableAlias = $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'companies');

        if (!is_string($companyTableAlias)) {
            throw new \LogicException('The table alias for '.MAUTIC_TABLE_PREFIX.'companies must be a string.');
        }

        $tableAlias = $this->generateRandomParameterName();
        $queryBuilder->leftJoin(
            $companyTableAlias,
            MAUTIC_TABLE_PREFIX.CompaniesSegments::TABLE_NAME,
            $tableAlias,
            $companyTableAlias.'.id = '.$tableAlias.'.company_id and '.$tableAlias.'.segment_id = :manually_unsubscribed_segment_id'
        )->setParameter('manually_unsubscribed_segment_id', $companySegment->getId())
            ->addJoinCondition($tableAlias, $queryBuilder->expr()->eq($tableAlias.'.manually_removed', ':true'))
            ->andWhere($queryBuilder->expr()->isNull($tableAlias.'.company_id'))
            ->setParameter('true', true, ParameterType::BOOLEAN);

        return $queryBuilder;
    }

    /**
     * Generate a unique parameter name.
     */
    private function generateRandomParameterName(): string
    {
        return $this->randomParameterName->generateRandomParameterName();
    }

    private function dispatchPluginFilteringEvent(ContactSegmentFilter $filter, QueryBuilder $queryBuilder): bool
    {
        if (!$this->dispatcher->hasListeners(CompanySegmentFilteringEvent::class)) {
            return false;
        }

        $alias = $this->generateRandomParameterName();
        $event = new CompanySegmentFilteringEvent($filter->contactSegmentFilterCrate, $alias, $queryBuilder, $this->entityManager);
        $this->dispatcher->dispatch($event);
        if ($event->isFilteringDone()) {
            $queryBuilder->addLogic($event->getSubQuery(), $filter->getGlue());

            return true;
        }

        return false;
    }

    /**
     * Returns array with plan for processing.
     *
     * @param array<int, int> $seen
     * @param array<int, int> $resolved
     *
     * @return array<int, int> New resolved
     *
     * @throws SegmentQueryException
     */
    private function getResolutionPlan(CompanySegment $companySegment, array $seen = [], array $resolved = []): array
    {
        $companySegmentId = $companySegment->getId();
        \assert(null !== $companySegmentId);
        $seen[] = $companySegmentId;

        if (!isset($this->dependencyMap[$companySegmentId])) {
            $this->dependencyMap[$companySegmentId] = $this->getSegmentEdges($companySegment);
        }

        $edges = $this->dependencyMap[$companySegmentId];

        foreach ($edges as $edge) {
            if (!in_array($edge, $resolved, true)) {
                if (in_array($edge, $seen, true)) {
                    throw new SegmentQueryException('Circular reference detected.');
                }

                $edgeCompanySegment = $this->companySegmentRepository->find($edge);

                if (null === $edgeCompanySegment) {
                    throw new SegmentQueryException('Dependent segment is not found.');
                }

                $resolved = $this->getResolutionPlan($edgeCompanySegment, $seen, $resolved);

                $this->companySegmentRepository->detachEntity($edgeCompanySegment);
            }
        }

        $resolved[] = $companySegmentId;

        return $resolved;
    }

    /**
     * @return array<int, string|int> Array of dependent segment IDs
     */
    private function getSegmentEdges(CompanySegment $companySegment): array
    {
        $segmentFilters = $companySegment->getFilters();
        $segmentEdges   = [];

        foreach ($segmentFilters as $segmentFilter) {
            if (isset($segmentFilter['field']) && CompanySegmentModel::PROPERTIES_FIELD === $segmentFilter['field']) {
                $bcFilter     = $segmentFilter['filter'] ?? [];
                $filterEdges  = $segmentFilter['properties']['filter'] ?? $bcFilter;
                $segmentEdges = array_merge($segmentEdges, $filterEdges);
            }
        }

        return $segmentEdges;
    }
}
