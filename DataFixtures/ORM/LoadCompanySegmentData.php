<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;

class LoadCompanySegmentData extends AbstractFixture implements OrderedFixtureInterface
{
    public const COMPANY_SEGMENT = 'company-segment';

    public function load(ObjectManager $manager): void
    {
        $adminUser = $this->getReference('admin-user', User::class);

        $companySegment = new CompanySegment();
        $companySegment->setName('United States');
        $companySegment->setPublicName('United States');
        $companySegment->setAlias('us');
        $companySegment->setCreatedBy($adminUser);
        $companySegment->setFilters([
            [
                'glue'     => 'and',
                'type'     => 'lookup',
                'field'    => 'country',
                'operator' => '=',
                'filter'   => 'United States',
                'display'  => '',
            ],
        ]);

        $this->setReference(self::COMPANY_SEGMENT, $companySegment);
        $manager->persist($companySegment);
        $manager->flush();
    }

    public function getOrder(): int
    {
        return 5;
    }
}
