<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;

class LoadCompanySegmentData extends AbstractFixture implements OrderedFixtureInterface
{
    public const COMPANY_SEGMENT_NO_FILTERS     = 'company-segment-1';
    public const COMPANY_SEGMENT_FILTER_REVENUE = 'company-segment-2';
    public const COMPANY_SEGMENT_DEPENDENT      = 'company-segment-3';

    public function load(ObjectManager $manager): void
    {
        $adminUser = $this->getReference('admin-user', User::class);

        $companySegment1 = new CompanySegment();
        $companySegment1->setName('Test 1');
        $companySegment1->setPublicName('Test 1');
        $companySegment1->setAlias('test1');
        $companySegment1->setCreatedBy($adminUser);
        $companySegment1->setFilters([]);
        $this->setReference(self::COMPANY_SEGMENT_NO_FILTERS, $companySegment1);
        $manager->persist($companySegment1);

        $companySegment = new CompanySegment();
        $companySegment->setName('Test 2');
        $companySegment->setPublicName('Test 2');
        $companySegment->setAlias('test2');
        $companySegment->setCreatedBy($adminUser);
        $companySegment->setFilters([
            [
                'glue'       => 'and',
                'operator'   => 'gte',
                'properties' => [
                    'filter' => '100000',
                ],
                'field'    => 'companyannual_revenue',
                'type'     => 'number',
                'object'   => 'company',
                'display'  => '',
            ],
        ]);
        $this->setReference(self::COMPANY_SEGMENT_FILTER_REVENUE, $companySegment);
        $manager->persist($companySegment);

        $manager->flush(); // to gain a new ID for segment "no filters"

        $companySegment = new CompanySegment();
        $companySegment->setName('Test 3');
        $companySegment->setPublicName('Test 3');
        $companySegment->setAlias('test3');
        $companySegment->setCreatedBy($adminUser);
        $companySegment->setFilters([
            [
                'glue'       => 'and',
                'operator'   => 'in',
                'properties' => [
                    'filter' => [''.$companySegment1->getId()],
                ],
                'field'    => CompanySegmentModel::PROPERTIES_FIELD,
                'type'     => CompanySegmentModel::PROPERTIES_FIELD,
                'object'   => CompanySegmentModel::PROPERTIES_FIELD,
                'display'  => '',
            ],
        ]);
        $this->setReference(self::COMPANY_SEGMENT_DEPENDENT, $companySegment);
        $manager->persist($companySegment);

        $manager->flush();
    }

    public function getOrder(): int
    {
        return 5;
    }
}
