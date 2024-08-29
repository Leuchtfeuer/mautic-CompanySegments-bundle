<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<CompaniesSegments>
 *
 * @see \Mautic\LeadBundle\Entity\ListLeadRepository
 */
class CompaniesSegmentsRepository extends CommonRepository
{
    /**
     * @param array<int, int> $segmentIds
     *
     * @return array<int, int>
     */
    public function getCompanyCount(array $segmentIds): array
    {
        $q = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $q->select('COUNT(1) as thecount, '.CompaniesSegments::RELATIONS_NAME.'.segment_id')
            ->from(MAUTIC_TABLE_PREFIX.CompaniesSegments::TABLE_NAME, CompaniesSegments::RELATIONS_NAME)
            ->groupBy(CompaniesSegments::RELATIONS_NAME.'.segment_id');

        if (1 === count($segmentIds)) {
            $q          = $this->forceUseIndex($q, MAUTIC_TABLE_PREFIX.'companies_segment_manually_removed');
            $expression = $q->expr()->eq(CompaniesSegments::RELATIONS_NAME.'.segment_id', (string) array_pop($segmentIds));
        } else {
            $expression = $q->expr()->in(CompaniesSegments::RELATIONS_NAME.'.segment_id', array_map(static function ($segmentId): string {
                return (string) $segmentId;
            }, $segmentIds));
        }

        $q->where($expression);
        $q->andWhere(CompaniesSegments::RELATIONS_NAME.'.manually_removed = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN);

        $result = $q->executeQuery()->fetchAllAssociative();

        $return = [];
        foreach ($result as $r) {
            \assert(is_numeric($r['segment_id']));
            \assert(is_numeric($r['thecount']));
            $return[(int) $r['segment_id']] = (int) $r['thecount'];
        }

        foreach ($segmentIds as $l) {
            if (!isset($return[$l])) {
                $return[$l] = 0;
            }
        }

        return $return;
    }

    private function forceUseIndex(QueryBuilder $qb, string $indexName): QueryBuilder
    {
        $fromPart = $qb->getQueryPart('from');
        \assert(is_array($fromPart));

        $fromPart[0]['alias'] = sprintf('%s USE INDEX (%s)', $fromPart[0]['alias'], $indexName);
        $qb->resetQueryPart('from');
        $qb->from($fromPart[0]['table'], $fromPart[0]['alias']);

        return $qb;
    }

    /**
     * @param int[] $segmentIds
     *
     * @return CompaniesSegments[]
     */
    public function getCompaniesSegmentsBySegmentIds(array $segmentIds): array
    {
        $result = $this->findBy([
            'companySegment'  => $segmentIds,
            'manuallyRemoved' => 0,
        ]);

        return array_values(array_unique($result, SORT_REGULAR));
    }
}
