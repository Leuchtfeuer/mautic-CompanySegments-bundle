<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Functional\Commands;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\ListLead;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateCompanySegmentsCommandTest extends MauticMysqlTestCase
{
    public function testUpdateCompanySegmentsCommandAddItemInNewSegment(): void
    {
        $companyGlobo  = $this->addCompany('Globo', 'contact@globo.com');
        $companySbt    = $this->addCompany('SBT', 'contact@sbt.com');
        $companyRecord = $this->addCompany('Record', 'contact@record.com');

        $leadOne   = $this->createLead('John Globo Doe', 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', 'leadthree@mautic.com');

        $leadOne->setCompany($companySbt);
        $leadOne->setPrimaryCompany($companyGlobo);

        $leadTwo->setPrimaryCompany($companyRecord);

        $leadThree->setPrimaryCompany($companyRecord);
        $leadThree->setCompany($companyGlobo);

        $this->em->persist($leadOne);
        $this->em->persist($leadTwo);
        $this->em->persist($leadThree);
        $this->em->flush();

        $companySegmentOne    = $this->addCompanySegment('Test Segment 1', 'test_segment');
        $companiesSegmentsOne = $this->addCompanyToSegments($companyGlobo, $companySegmentOne);
        $filters              = [
            'filters' => [
                'glue'       => 'and',
                'operator'   => 'in',
                'properties' => [
                    'filter' => [$companySegmentOne->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];
        $companySegmentTwo             = $this->addCompanySegment('Test Segment 2', 'test_segment2', true, $filters);
        $resultCompaniesSegmentsBefore = $this->em->getRepository(CompaniesSegments::class)->findAll();

        self::assertCount(1, $resultCompaniesSegmentsBefore);

        $kernel        = static::getContainer()->get('kernel');
        assert($kernel instanceof \Symfony\Component\HttpKernel\KernelInterface);
        $application   = new Application($kernel);
        $application->setAutoExit(false);
        $command       = $application->find('leuchtfeuer:abm:segments-update');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $resultCompaniesSegmentsAfter = $this->em->getRepository(CompaniesSegments::class)->findAll();
        self::assertCount(2, $resultCompaniesSegmentsAfter);
        assert($resultCompaniesSegmentsAfter[0] instanceof CompaniesSegments);
        assert($resultCompaniesSegmentsAfter[1] instanceof CompaniesSegments);
        self::assertEquals($resultCompaniesSegmentsAfter[0]->getCompany()->getId(), $resultCompaniesSegmentsAfter[1]->getCompany()->getId());
        self::assertEquals($resultCompaniesSegmentsAfter[1]->getCompanySegment()->getId(), $companySegmentTwo->getId());
    }

    public function testUpdateCompanySegmentsCommandRemoveItemInNewSegment(): void
    {
        $companyGlobo  = $this->addCompany('Globo', 'contact@globo.com');
        $companySbt    = $this->addCompany('SBT', 'contact@sbt.com');
        $companyRecord = $this->addCompany('Record', 'contact@record.com');

        $leadOne   = $this->createLead('John Globo Doe', 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', 'leadthree@mautic.com');
        $leadFour  = $this->createLead('Braw Doe', 'leadfour@mautic.com');

        $leadOne->setCompany($companySbt);
        $leadOne->setPrimaryCompany($companyGlobo);

        $leadTwo->setPrimaryCompany($companyRecord);

        $leadThree->setPrimaryCompany($companyRecord);

        $leadFour->setPrimaryCompany($companySbt);

        $this->em->persist($leadOne);
        $this->em->persist($leadTwo);
        $this->em->persist($leadThree);
        $this->em->persist($leadFour);
        $this->em->flush();

        $companySegmentOne    = $this->addCompanySegment('Test Company Segment 1', 'test_comp_segment');
        $companiesSegmentsOne = $this->addCompanyToSegments($companyGlobo, $companySegmentOne);
        $filters              = [
            'filters' => [
                'glue'       => 'and',
                'operator'   => 'in',
                'properties' => [
                    'filter' => [$companySegmentOne->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];
        $companySegmentTwo             = $this->addCompanySegment('Test Company Segment 2', 'test_comp_segment2', true, $filters);
        $segmentOne                    = $this->addSegment('Test Segment 1', 'test_segment');
        $leadSegmentOne                = $this->addLeadSegment($leadOne, $segmentOne);
        $resultCompaniesSegmentsBefore = $this->em->getRepository(CompaniesSegments::class)->findAll();
        self::assertCount(1, $resultCompaniesSegmentsBefore);

        $filters = [
            [
                'glue'       => 'and',
                'operator'   => '!=',
                'properties' => [
                    'filter' => 'asdasdaadasd',
                ],
                'field'  => 'address1',
                'type'   => 'text',
                'object' => 'lead',
            ],
            [
                'glue'       => 'and',
                'operator'   => '!in',
                'properties' => [
                    'filter' => [$companySegmentTwo->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];

        $leadListTwo = $this->addSegment('Test Segment 2', 'test_segment2', true, $filters);
        $leadList    = $this->em->getRepository(LeadList::class)->findAll();

        self::assertCount(2, $leadList);

        $leadListModel = static::getContainer()->get('mautic.lead.model.list');
        assert($leadListModel instanceof \Mautic\LeadBundle\Model\ListModel);
        $leadListTotalBefore = $leadListModel->getListLeadRepository()->findAll();
        self::assertCount(1, $leadListTotalBefore);

        $kernel        = static::getContainer()->get('kernel');
        assert($kernel instanceof \Symfony\Component\HttpKernel\KernelInterface);
        $application   = new Application($kernel);
        $application->setAutoExit(false);
        $command       = $application->find('leuchtfeuer:abm:segments-update');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $resultCompaniesSegmentsAfter = $this->em->getRepository(CompaniesSegments::class)->findAll();
        self::assertCount(2, $resultCompaniesSegmentsAfter);

        $kernel        = static::getContainer()->get('kernel');
        assert($kernel instanceof \Symfony\Component\HttpKernel\KernelInterface);
        $application   = new Application($kernel);
        $application->setAutoExit(false);
        $command       = $application->find('mautic:segments:update');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $leadListTotalAfter = $leadListModel->getListLeadRepository()->findAll();
        self::assertCount(5, $leadListTotalAfter);
    }

    private function createLead(string $name, string $email, ?Company $companyName = null): Lead
    {
        $lead = new Lead();
        $lead->setFirstname($name);
        $lead->setLastname($name.' lastname');
        $lead->setEmail($email);
        if (null !== $companyName) {
            $lead->setCompany($companyName);
        }
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }

    /**
     * @param array<mixed> $filters
     */
    private function addSegment(string $name, string $alias, bool $isPublished = true, array $filters = []): LeadList
    {
        $leadList = new LeadList();
        $leadList->setPublicName($name);
        $leadList->setName($name);
        $leadList->setAlias($alias);
        $leadList->setIsPublished($isPublished);
        if ([] !== $filters) {
            $leadList->setFilters($filters);
        }
        $this->em->persist($leadList);
        $this->em->flush();

        return $leadList;
    }

    private function addLeadSegment(Lead $contact, LeadList $segment): ListLead
    {
        // Add contact to segment:
        $segmentContact = new ListLead();
        $segmentContact->setLead($contact);
        $segmentContact->setList($segment);
        $segmentContact->setDateAdded(new \DateTime());
        $this->em->persist($segmentContact);
        $this->em->flush();

        return $segmentContact;
    }

    /**
     * @param array<array<mixed>> $filters
     */
    private function addCompanySegment(string $name, string $alias, bool $isPublished = true, array $filters = []): CompanySegment
    {
        $companySegment = new CompanySegment();
        $companySegment->setName($name);
        $companySegment->setAlias($alias);
        $companySegment->setIsPublished($isPublished);
        if ([] !== $filters) {
            $companySegment->setFilters($filters);
        }
        $this->em->persist($companySegment);
        $this->em->flush();

        return $companySegment;
    }

    private function addCompany(string $name, string $email): Company
    {
        $company = new Company();
        $company->setName($name);
        $company->setEmail($email);
        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }

    private function addCompanyToSegments(Company $company, CompanySegment $companySegment): CompaniesSegments
    {
        $companiesSegments = new CompaniesSegments();
        $companiesSegments->setCompany($company);
        $companiesSegments->setCompanySegment($companySegment);
        $companiesSegments->setDateAdded(new \DateTime());
        $this->em->persist($companiesSegments);
        $this->em->flush();

        return $companiesSegments;
    }
}
