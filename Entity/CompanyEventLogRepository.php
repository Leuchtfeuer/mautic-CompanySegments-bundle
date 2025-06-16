<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\LeadBundle\Entity\Company;

class CompanyEventLogRepository extends CommonRepository
{
    use TimelineTrait;

    /**
     * Returns array with failed rows.
     *
     * @param string $importId
     * @param string $bundle
     * @param string $object
     * @param array<string,mixed> $args
     *
     * @return array<mixed>
     */
    public function getFailedRows($importId, array $args = [], $bundle = 'company', $object = 'import'): array
    {
        return $this->getSpecificRows($importId, 'failed', $args, $bundle, $object);
    }

    /**
     * @param array<string,mixed> $args
     * @return array<mixed>
     */
    public function getEntities(array $args = []): array
    {
        $entities = parent::getEntities($args);

        if ($entities instanceof \Traversable) {
            $entities = iterator_to_array($entities);
        }

        foreach ($entities as $key => $row) {
            if (
                isset($row['properties'])
                && is_array($row['properties'])
                && isset($row['properties']['error'])
                && preg_match('/SQLSTATE\[\w+\]: (.*)/', $row['properties']['error'], $matches)
            ) {
                if (isset($entities[$key]['properties']['error'])) {
                    $entities[$key]['properties']['error'] = $matches[1];
                } elseif (is_object($entities[$key]) && isset($entities[$key]->properties->error)) {
                    $entities[$key]->properties->error = $matches[1];
                }
            }
        }

        return $entities;
    }

    /**
     * Returns paginator with specific type of rows.
     *
     * @param string|int $objectId
     * @param string $bundle
     * @param string $object
     * @param string|int $action
     * @param array<string,mixed> $args
     *
     * @return array<mixed>
     */
    public function getSpecificRows($objectId, $action, array $args = [], $bundle = 'lead', $object = 'import'): array
    {
        return $this->getEntities(
            array_merge(
                [
                    'start'          => 0,
                    'limit'          => 100,
                    'orderBy'        => $this->getTableAlias().'.dateAdded',
                    'orderByDir'     => 'ASC',
                    'filter'         => [
                        'force' => [
                            [
                                'column' => $this->getTableAlias().'.bundle',
                                'expr'   => 'eq',
                                'value'  => $bundle,
                            ],
                            [
                                'column' => $this->getTableAlias().'.object',
                                'expr'   => 'eq',
                                'value'  => $object,
                            ],
                            [
                                'column' => $this->getTableAlias().'.action',
                                'expr'   => 'eq',
                                'value'  => $action,
                            ],
                            [
                                'column' => $this->getTableAlias().'.objectId',
                                'expr'   => 'eq',
                                'value'  => $objectId,
                            ],
                        ],
                    ],
                    'hydration_mode' => 'HYDRATE_ARRAY',
                ],
                $args
            )
        );
    }

    /**
     * @param ?string           $bundle
     * @param ?string           $object
     * @param array<string,string>|string|null $actions
     * @param array<string,string> $options
     *
     * @return array<mixed>
     */
    public function getEvents(?Company $company = null, $bundle = null, $object = null, $actions = null, array $options = [])
    {
        $alias = $this->getTableAlias();
        $qb    = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select('*')
            ->from(MAUTIC_TABLE_PREFIX.'company_event_log', $alias);

        if ($company) {
            $qb->andWhere($alias.'.company_id = :company')
                ->setParameter('company', $company->getId());
        }

        if ($bundle) {
            $qb->andWhere($alias.'.bundle = :bundle')
                ->setParameter('bundle', $bundle);
        }

        if ($object) {
            $qb->andWhere($alias.'.object = :object')
                ->setParameter('object', $object);
        }

        if ($actions) {
            if (is_array($actions)) {
                $qb->andWhere(
                    $qb->expr()->in($alias.'.action', ':actions')
                )
                    ->setParameter('actions', $actions, ArrayParameterType::STRING);
            } else {
                $qb->andWhere($alias.'.action = :action')
                    ->setParameter('action', $actions);
            }
        }

        if (!empty($options['search'])) {
            $qb->andWhere($qb->expr()->like('LOWER('.$alias.'.properties)', $qb->expr()->literal('%'.strtolower($options['search']).'%')));
        }

        return $this->getTimelineResults($qb, $options, $alias.'.action', $alias.'.date_added', [], ['date_added'], null, $alias.'.id');
    }

    /**
     * Updates lead ID (e.g. after a company merge).
     *
     * @param int $fromCompanyId
     * @param int $toCompanyId
     */
    public function updateCompany($fromCompanyId, $toCompanyId): void
    {
        $toCompanyId = (int) $toCompanyId;
        $toCompanyId = (string) $toCompanyId;
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->update(MAUTIC_TABLE_PREFIX.'company_event_log')
            ->set('company_id', $toCompanyId)
            ->where('company_id = '.(int) $fromCompanyId)
            ->executeStatement();
    }

    /**
     * Defines default table alias for company_event_log table.
     */
    public function getTableAlias(): string
    {
        return 'cel';
    }
}
