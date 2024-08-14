<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\UserBundle\Entity\User;

/**
 * @extends CommonRepository<CompanySegment>
 */
class CompanySegmentRepository extends CommonRepository
{
    /**
     * The original method name is `getLists`.
     *
     * @return array<array{id: string, name: string, alias: string}>
     */
    public function getSegments(?User $user = null, ?string $alias = null, ?int $id = null): array
    {
        $q = $this->getEntityManager()->createQueryBuilder()
            ->from(CompanySegment::class, $this->getTableAlias(), $this->getTableAlias().'.id');

        $q->select('partial '.$this->getTableAlias().'.{id, name, alias}')
            ->andWhere($q->expr()->eq($this->getTableAlias().'.isPublished', ':true'))
            ->setParameter('true', true, 'boolean');

        if (null !== $user) {
            $q->andWhere(
                $q->expr()->orX(
                    $q->expr()->eq($this->getTableAlias().'.createdBy', ':user')
                )
            );
            $q->setParameter('user', $user->getId());
        }

        if (null !== $alias && '' !== $alias) {
            $q->andWhere($this->getTableAlias().'.alias = :alias');
            $q->setParameter('alias', $alias);
        }

        if (null !== $id && $id > 0) {
            $q->andWhere(
                $q->expr()->neq($this->getTableAlias().'.id', $id)
            );
        }

        $q->orderBy($this->getTableAlias().'.name');

        /** @var array<array{id: string, name: string, alias: string}> $result */
        $result = $q->getQuery()->getArrayResult();

        return $result;
    }

    /**
     * @param array<int, int> $segmentIds
     *
     * @return array<int, int>
     */
    public function getCompanyCount(array $segmentIds): array
    {
        $q = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $q->select('COUNT(1) as thecount, cs_ref.segment_id')
            ->from(MAUTIC_TABLE_PREFIX.CompanySegment::RELATION_TABLE_NAME, 'cs_ref')
            ->groupBy('cs_ref.segment_id');

        if (1 === count($segmentIds)) {
            $expression = $q->expr()->eq('cs_ref.segment_id', (string) array_pop($segmentIds));
        } else {
            $expression = $q->expr()->in('cs_ref.segment_id', array_map(static function ($segmentId): string {
                return (string) $segmentId;
            }, $segmentIds));
        }

        $q->where($expression);

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

    public function getTableAlias(): string
    {
        return CompanySegment::DEFAULT_ALIAS;
    }

    /**
     * The override as in \Mautic\LeadBundle\Entity\LeadListRepository::addSearchCommandWhereClause.
     * Stripped conditions for search.
     *
     * @param \Doctrine\ORM\QueryBuilder|\Doctrine\DBAL\Query\QueryBuilder $q
     *
     * @phpstan-param mixed $filter
     *
     * @return array<mixed>
     */
    protected function addSearchCommandWhereClause($q, $filter): array
    {
        \assert($filter instanceof \stdClass);
        [$expr, $parameters] = parent::addStandardSearchCommandWhereClause($q, $filter);
        if ($expr) {
            return [$expr, $parameters];
        }

        $command         = $filter->command;
        $unique          = $this->generateRandomParameterName();
        $returnParameter = false; // returning a parameter that is not used will lead to a Doctrine error

        switch ($command) {
            case $this->translator->trans('mautic.core.searchcommand.name'):
            case $this->translator->trans('mautic.core.searchcommand.name', [], null, 'en_US'):
                $expr            = $q->expr()->like($this->getTableAlias().'.name', ':'.$unique);
                $returnParameter = true;
                break;
        }

        if ($returnParameter) {
            $string     = ($filter->strict) ? $filter->string : "%{$filter->string}%";
            $parameters = [$unique => $string];
        }

        return [
            $expr,
            $parameters,
        ];
    }

    /**
     * @return string[]|array<string, string[]>
     */
    public function getSearchCommands(): array
    {
        $commands = [
            'mautic.core.searchcommand.ispublished',
            'mautic.core.searchcommand.isunpublished',
            'mautic.core.searchcommand.name',
            'mautic.core.searchcommand.category',
        ];

        return array_merge($commands, parent::getSearchCommands());
    }

    /**
     * This function is required for both generating reports by company segment and for the LeuchtfeuerCompanyListWidgetBundle.
     *
     * @param array<int>|array{} $ids list of IDs for company segments
     *
     * @return array<CompanySegment> array of CompanySegment objects
     */
    public function getSegmentObjectsViaListOfIDs(array $ids = []): array
    {
        $q = $this->getEntityManager()->createQueryBuilder()
            ->from(CompanySegment::class, 'cs', 'cs.id');

        $q->select('cs')
            ->andWhere($q->expr()->eq('cs.isPublished', ':true'))
            ->setParameter('true', true, 'boolean');

        if (!empty($ids)) {
            $q->andWhere($q->expr()->in('cs.id', $ids));
        }

        return $q->getQuery()->getResult();
    }
}
