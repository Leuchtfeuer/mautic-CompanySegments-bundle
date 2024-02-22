<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\UserBundle\Entity\User;

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
            ->from(CompanySegment::class, 'cs', 'cs.id');

        $q->select('partial cs.{id, name, alias}')
            ->andWhere($q->expr()->eq('cs.isPublished', ':true'))
            ->setParameter('true', true, 'boolean');

        if (null !== $user) {
            $q->andWhere(
                $q->expr()->orX(
                    $q->expr()->eq('cs.isGlobal', ':true'),
                    $q->expr()->eq('cs.createdBy', ':user')
                )
            );
            $q->setParameter('user', $user->getId());
        }

        if (null !== $alias && '' !== $alias) {
            $q->andWhere('cs.alias = :alias');
            $q->setParameter('alias', $alias);
        }

        if (null !== $id && $id > 0) {
            $q->andWhere(
                $q->expr()->neq('cs.id', $id)
            );
        }

        $q->orderBy('cs.name');

        return $q->getQuery()->getArrayResult();
    }
}
