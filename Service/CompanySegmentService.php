<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Service;

use Mautic\LeadBundle\Segment\ContactSegmentFilterFactory;
use Mautic\LeadBundle\Segment\Exception\SegmentQueryException;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\DTO\CompanySegmentAsLeadSegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Segment\Query\CompanyBatchLimiterTrait;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Segment\Query\CompanySegmentQueryBuilder;
use Psr\Log\LoggerInterface;

class CompanySegmentService
{
    use CompanyBatchLimiterTrait;

    public function __construct(
        private ContactSegmentFilterFactory $contactSegmentFilterFactory,
        private CompanySegmentQueryBuilder $companySegmentQueryBuilder,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @return array<int, array{count: string, maxId: string, minId: string}>
     *
     * @throws \Exception
     */
    public function getTotalCompanySegmentsCompaniesCount(CompanySegment $companySegment): array
    {
        $contactSegment = new CompanySegmentAsLeadSegment($companySegment);
        $segmentFilters = $this->contactSegmentFilterFactory->getSegmentFilters($contactSegment);

        $companySegmentId = $companySegment->getId();
        \assert(null !== $companySegmentId);
        if (0 === count($segmentFilters)) {
            $this->logger->debug('Company Segment QB: Segment has no filters', ['segmentId' => $companySegmentId]);

            return [
                $companySegmentId => [
                    'count' => '0',
                    'maxId' => '0',
                    'minId' => '0',
                ],
            ];
        }

        $qb = $this->getTotalSegmentCompaniesQuery($companySegment);

        $qb = $this->companySegmentQueryBuilder->wrapInCount($qb);

        $this->logger->debug('Company Segment QB: Create SQL: '.$qb->getDebugOutput(), ['segmentId' => $companySegmentId]);

        $result = $this->timedFetch($qb, $companySegmentId);
        \assert(3 === count($result));
        \assert(is_string($result['count']) && is_numeric($result['count']));
        \assert(is_string($result['maxId']) && is_numeric($result['maxId']));
        \assert(is_string($result['minId']) && is_numeric($result['minId']));

        return [$companySegmentId => $result];
    }

    /**
     * @param array<string, mixed> $batchLimiters
     *
     * @return array<int, mixed[]>
     *
     * @throws \Exception
     */
    public function getNewCompanySegmentsCompanyCount(CompanySegment $companySegment, array $batchLimiters): array
    {
        $contactSegment = new CompanySegmentAsLeadSegment($companySegment);
        $segmentFilters = $this->contactSegmentFilterFactory->getSegmentFilters(
            $contactSegment,
            $batchLimiters
        );

        $companySegmentId = $companySegment->getId();
        \assert(null !== $companySegmentId);
        if (0 === count($segmentFilters)) {
            $this->logger->debug('Company Segment QB: Segment has no filters', ['segmentId' => $companySegmentId]);

            return [
                $companySegmentId => [
                    'count' => '0',
                    'maxId' => '0',
                ],
            ];
        }

        $qb = $this->getNewSegmentContactsQuery($companySegment, $batchLimiters);

        $this->addMinMaxLimiters($qb, $batchLimiters, 'companies', 'id');

        $qb = $this->companySegmentQueryBuilder->wrapInCount($qb);

        $this->logger->debug('Company Segment QB: Create SQL: '.$qb->getDebugOutput(), ['segmentId' => $companySegmentId]);

        $result = $this->timedFetch($qb, $companySegmentId);

        return [$companySegmentId => $result];
    }

    /**
     * @param array<string, mixed> $batchLimiters
     *
     * @return array<int, mixed[]>
     *
     * @throws \Exception
     * @throws SegmentQueryException
     */
    public function getNewCompanySegmentCompanies(CompanySegment $segment, array $batchLimiters, int $limit = 1000): array
    {
        $queryBuilder = $this->getNewCompanySegmentCompaniesQueryBuilder($segment, $batchLimiters);
        $queryBuilder->setMaxResults($limit);

        $segmentId = $segment->getId();
        \assert(null !== $segmentId);
        $result = $this->timedFetchAll($queryBuilder, $segmentId);

        return [$segmentId => $result];
    }

    /**
     * @param array<string, mixed> $batchLimiters
     *
     * @return array<int, array<mixed>>
     *
     * @throws SegmentQueryException
     * @throws \Exception
     */
    public function getOrphanedCompanySegmentCompaniesCount(CompanySegment $segment, array $batchLimiters = []): array
    {
        $queryBuilder = $this->getOrphanedCompanySegmentCompaniesQueryBuilder($segment, $batchLimiters);
        $queryBuilder = $this->companySegmentQueryBuilder->wrapInCount($queryBuilder);

        $segmentId = $segment->getId();
        \assert(null !== $segmentId);
        $this->logger->debug('Company Segment QB: Orphan Companies Count SQL: '.$queryBuilder->getDebugOutput(), ['segmentId' => $segmentId]);

        $result = $this->timedFetch($queryBuilder, $segmentId);

        return [$segmentId => $result];
    }

    /**
     * @param array<string, mixed> $batchLimiters
     *
     * @return array<int, array<mixed>>
     *
     * @throws SegmentQueryException
     * @throws \Exception
     */
    public function getOrphanedCompanySegmentCompanies(CompanySegment $segment, array $batchLimiters = [], ?int $limit = null): array
    {
        $queryBuilder = $this->getOrphanedCompanySegmentCompaniesQueryBuilder($segment, $batchLimiters, $limit);

        $segmentId = $segment->getId();
        \assert(null !== $segmentId);
        $this->logger->debug('Company Segment QB: Orphan Companies SQL: '.$queryBuilder->getDebugOutput(), ['segmentId' => $segmentId]);

        $result = $this->timedFetchAll($queryBuilder, $segmentId);

        return [$segmentId => $result];
    }

    /**
     * @param array<string, mixed> $batchLimiters
     */
    private function getNewCompanySegmentCompaniesQueryBuilder(CompanySegment $segment, array $batchLimiters, bool $addNewContactsRestrictions = true): QueryBuilder
    {
        $queryBuilder        = $this->getNewSegmentContactsQuery($segment, $batchLimiters, $addNewContactsRestrictions);
        $companiesTableAlias = $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'companies');
        \assert(is_string($companiesTableAlias));

        // Prepend the DISTINCT to the beginning of the select array
        $select = $queryBuilder->getQueryPart('select');
        \assert(is_array($select));

        // We are removing it because we will have to add it later
        // to make sure it's the first column in the query
        $key = array_search($companiesTableAlias.'.id', $select, true);
        if (false !== $key) {
            unset($select[$key]);
        }

        // We only need to use distinct if we join other tables to the companies table
        $join     = $queryBuilder->getQueryPart('join');
        $distinct = is_array($join) && (0 < count($join)) ? 'DISTINCT ' : '';
        // Make sure that companies.id is the first column
        array_unshift($select, $distinct.$companiesTableAlias.'.id');
        $queryBuilder->resetQueryPart('select');
        $queryBuilder->select($select);

        $this->logger->debug('Company Segment QB: Create Companies SQL: '.$queryBuilder->getDebugOutput(), ['segmentId' => $segment->getId()]);

        $this->addMinMaxLimiters($queryBuilder, $batchLimiters, 'companies', 'id');

        if (isset($batchLimiters['dateTime'])) {
            // Only companies in the list at the time of count
            $queryBuilder->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->lte($companiesTableAlias.'.date_added', $queryBuilder->expr()->literal($batchLimiters['dateTime'])),
                    $queryBuilder->expr()->isNull($companiesTableAlias.'.date_added')
                )
            );
        }

        return $queryBuilder;
    }

    /**
     * @param array<string, mixed> $batchLimiters
     *
     * @throws \Exception
     */
    private function getNewSegmentContactsQuery(CompanySegment $segment, array $batchLimiters = [], bool $addNewContactsRestrictions = true): QueryBuilder
    {
        $contactSegment = new CompanySegmentAsLeadSegment($segment);
        $queryBuilder   = $this->companySegmentQueryBuilder->assembleCompaniesSegmentQueryBuilder(
            $segment,
            $this->contactSegmentFilterFactory->getSegmentFilters($contactSegment, $batchLimiters)
        );

        if ($addNewContactsRestrictions) {
            $queryBuilder = $this->companySegmentQueryBuilder->addNewCompaniesRestrictions($queryBuilder, $segment, $batchLimiters);
        }

        $this->companySegmentQueryBuilder->queryBuilderGenerated($segment, $queryBuilder);

        return $queryBuilder;
    }

    /**
     * @throws \Exception
     *
     * @see \Mautic\LeadBundle\Segment\ContactSegmentService::getTotalSegmentContactsQuery
     */
    private function getTotalSegmentCompaniesQuery(CompanySegment $companySegment): QueryBuilder
    {
        $contactSegment = new CompanySegmentAsLeadSegment($companySegment);
        $segmentFilters = $this->contactSegmentFilterFactory->getSegmentFilters($contactSegment);

        $queryBuilder = $this->companySegmentQueryBuilder->assembleCompaniesSegmentQueryBuilder($companySegment, $segmentFilters);
        $queryBuilder = $this->companySegmentQueryBuilder->addManuallySubscribedQuery($queryBuilder, $companySegment);

        return $this->companySegmentQueryBuilder->addManuallyUnsubscribedQuery($queryBuilder, $companySegment);
    }

    /**
     * @param array<string, mixed> $batchLimiters
     *
     * @throws \Exception
     */
    private function getOrphanedCompanySegmentCompaniesQueryBuilder(CompanySegment $companySegment, array $batchLimiters = [], ?int $limit = null): QueryBuilder
    {
        $contactSegment = new CompanySegmentAsLeadSegment($companySegment);
        $segmentFilters = $this->contactSegmentFilterFactory->getSegmentFilters($contactSegment, $batchLimiters);

        $queryBuilder = $this->companySegmentQueryBuilder->assembleCompaniesSegmentQueryBuilder($companySegment, $segmentFilters);

        $this->addMinMaxLimiters($queryBuilder, $batchLimiters, 'companies', 'id');

        $this->companySegmentQueryBuilder->queryBuilderGenerated($companySegment, $queryBuilder);

        $expr = $queryBuilder->expr();
        \assert(null !== $expr);

        $qbO  = $queryBuilder->createQueryBuilder();
        $qbO->select('orp.company_id as id, orp.segment_id');
        $qbO->from(MAUTIC_TABLE_PREFIX.CompaniesSegments::TABLE_NAME, 'orp');
        $qbO->setParameters($queryBuilder->getParameters(), $queryBuilder->getParameterTypes());
        $qbO->andWhere($expr->eq('orp.segment_id', ':orpsegid'));
        $qbO->andWhere($expr->eq('orp.manually_added', $expr->literal(0)));
        $qbO->andWhere($expr->notIn('orp.company_id', $queryBuilder->getSQL()));
        $qbO->setParameter('orpsegid', $companySegment->getId());
        $this->addMinMaxLimiters($qbO, $batchLimiters, CompaniesSegments::TABLE_NAME, 'company_id');

        if (null !== $limit && $limit > 0) {
            $qbO->setMaxResults($limit);
        }

        return $qbO;
    }

    /***** DEBUG *****/

    /**
     * Formatting helper.
     */
    private function formatPeriod(float $inputSeconds): string
    {
        $now = \DateTime::createFromFormat('U.u', number_format($inputSeconds, 6, '.', ''));
        \assert(false !== $now);

        return $now->format('H:i:s.u');
    }

    /**
     * @return array<mixed>
     *
     * @throws \Exception
     */
    private function timedFetch(QueryBuilder $qb, int $segmentId): array
    {
        try {
            $start = microtime(true);

            $result = $qb->executeQuery()->fetchAssociative();
            \assert(is_array($result));

            $end = microtime(true) - $start;

            $this->logger->debug('Company Segment QB: Query took: '.$this->formatPeriod($end).', Result count: '.count($result), ['segmentId' => $segmentId]);
        } catch (\Exception $e) {
            $this->logger->error(
                'Company Segment QB: Query Exception: '.$e->getMessage(),
                [
                    'query'      => $qb->getSQL(),
                    'parameters' => $qb->getParameters(),
                ]
            );
            throw $e;
        }

        return $result;
    }

    /**
     * @return array<mixed>
     *
     * @throws \Exception
     */
    private function timedFetchAll(QueryBuilder $qb, int $segmentId): array
    {
        try {
            $start  = microtime(true);
            $result = $qb->executeQuery()->fetchAllAssociative();

            $end = microtime(true) - $start;

            $this->logger->debug(
                'Segment QB: Query took: '.$this->formatPeriod($end).'ms. Result count: '.count($result),
                ['segmentId' => $segmentId]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'Segment QB: Query Exception: '.$e->getMessage(),
                [
                    'query'      => $qb->getSQL(),
                    'parameters' => $qb->getParameters(),
                ]
            );
            throw $e;
        }

        return $result;
    }
}
