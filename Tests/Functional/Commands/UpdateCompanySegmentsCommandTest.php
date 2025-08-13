<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Functional\Commands;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
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

        $companySegmentOne    = $this->createCompanySegment('Test Segment 1', 'test_segment');
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
        $companySegmentTwo             = $this->createCompanySegment('Test Segment 2', 'test_segment2', true, $filters);
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

    public function testUpdateLeadSegmentsUsingExcludeACompanySegment(): void
    {
        $companyGlobo  = $this->addCompany('Globo', 'contact@globo.com');
        $companySbt    = $this->addCompany('SBT', 'contact@sbt.com');
        $companyRecord = $this->addCompany('Record', 'contact@record.com');

        $leadOne   = $this->createLead('John Globo Doe', 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', 'leadthree@mautic.com');
        $leadFour  = $this->createLead('Braw Doe', 'leadfour@mautic.com');

        $companyLeadGloboLeadOne = $this->addLeadToCompany($companyGlobo, $leadOne);
        $companyLeadGloboLeadTwo = $this->addLeadToCompany($companyGlobo, $leadTwo);
        $companyLeadSbtLeadThree = $this->addLeadToCompany($companySbt, $leadThree);
        $companyLeadSbtLeadFour  = $this->addLeadToCompany($companySbt, $leadFour);

        $totalCompanyLeadsBefore = $this->em->getRepository(CompanyLead::class)->findAll();
        self::assertCount(4, $totalCompanyLeadsBefore);
        $companySegmentOne             = $this->createCompanySegment('Test Company Segment 1', 'test_comp_segment');
        $companiesSegmentsOne          = $this->addCompanyToSegments($companyGlobo, $companySegmentOne);
        $resultCompaniesSegmentsBefore = $this->em->getRepository(CompaniesSegments::class)->findAll();
        self::assertCount(1, $resultCompaniesSegmentsBefore);

        $filtersToLeadSegment = [
            [
                'glue'       => 'and',
                'operator'   => '!in',
                'properties' => [
                    'filter' => [$companySegmentOne->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];

        // Start Lead Segments
        $leadSegmentOne                = $this->createLeadSegment('Test Segment 1', 'test_segment', true, $filtersToLeadSegment);
        $leadListModel                 = static::getContainer()->get('mautic.lead.model.list');
        assert($leadListModel instanceof \Mautic\LeadBundle\Model\ListModel);
        // Get total of lead in list ( segments )
        $leadListTotalBefore = $leadListModel->getListLeadRepository()->findAll();
        // result zero because was add in $leadSegmentOne
        self::assertCount(0, $leadListTotalBefore);

        // COMMAND MAUTIC SEG UPDATE
        $kernel        = static::getContainer()->get('kernel');
        assert($kernel instanceof \Symfony\Component\HttpKernel\KernelInterface);
        $application   = new Application($kernel);
        $application->setAutoExit(false);
        $command       = $application->find('mautic:segments:update');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        self::assertStringContainsString('2 total contact(s) to be added', $commandTester->getDisplay());

        $leadListTotalAfter = $leadListModel->getListLeadRepository()->findAll();
        self::assertCount(2, $leadListTotalAfter);
    }

    public function testUpdateCompanySegmentsAndUpdateLeadSegmentCommandAddingAllContactsLessCompanSegment(): void
    {
        $companyGlobo  = $this->addCompany('Globo', 'contact@globo.com');
        $companySbt    = $this->addCompany('SBT', 'contact@sbt.com');
        $companyRecord = $this->addCompany('Record', 'contact@record.com');

        $leadOne   = $this->createLead('John Globo Doe', 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', 'leadthree@mautic.com');
        $leadFour  = $this->createLead('Braw Doe', 'leadfour@mautic.com');

        $companyLeadGloboLeadOne = $this->addLeadToCompany($companyGlobo, $leadOne);
        $companyLeadGloboLeadTwo = $this->addLeadToCompany($companyGlobo, $leadTwo);
        $companyLeadSbtLeadThree = $this->addLeadToCompany($companySbt, $leadThree);
        $companyLeadSbtLeadFour  = $this->addLeadToCompany($companySbt, $leadFour);

        $totalCompanyLeadsBefore = $this->em->getRepository(CompanyLead::class)->findAll();
        self::assertCount(4, $totalCompanyLeadsBefore);

        $companySegmentOne    = $this->createCompanySegment('Test Company Segment 1', 'test_comp_segment');

        // globo added in Company Segment 1
        $companiesSegmentsOne          = $this->addCompanyToSegments($companyGlobo, $companySegmentOne);
        $resultCompaniesSegmentsBefore = $this->em->getRepository(CompaniesSegments::class)->findAll();
        self::assertCount(1, $resultCompaniesSegmentsBefore);

        $filtersToCompanySegment  = [
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

        // globo will be added in cs2 after command
        $companySegmentTwo             = $this->createCompanySegment('Test Company Segment 2', 'test_comp_segment2', true, $filtersToCompanySegment);

        // Start Lead Segments
        $leadSegmentOne                = $this->createLeadSegment('Test Segment 1', 'test_segment');

        $filtersToLeadSegment = [
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

        $leadSegmentTwo = $this->createLeadSegment('Test Segment 2', 'test_segment2', true, $filtersToLeadSegment);

        $leadListModel = static::getContainer()->get('mautic.lead.model.list');
        assert($leadListModel instanceof \Mautic\LeadBundle\Model\ListModel);

        // Get total of lead in list ( segments )
        $leadListTotalBefore = $leadListModel->getListLeadRepository()->findAll();
        // result zero because was add in $leadSegmentOne
        self::assertCount(0, $leadListTotalBefore);

        // COMMAND ABM SEG UPDATE
        $kernel        = static::getContainer()->get('kernel');
        assert($kernel instanceof \Symfony\Component\HttpKernel\KernelInterface);
        $application   = new Application($kernel);
        $application->setAutoExit(false);
        $command       = $application->find('leuchtfeuer:abm:segments-update');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        self::assertStringContainsString('1 total company(es) to be added', $commandTester->getDisplay());

        $resultCompaniesSegmentsAfter = $this->em->getRepository(CompaniesSegments::class)->findAll();

        // globo was added now in second company segment
        self::assertCount(2, $resultCompaniesSegmentsAfter);

        // COMMAND MAUTIC SEG UPDATE
        $kernel        = static::getContainer()->get('kernel');
        assert($kernel instanceof \Symfony\Component\HttpKernel\KernelInterface);
        $application   = new Application($kernel);
        $application->setAutoExit(false);
        $command       = $application->find('mautic:segments:update');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        self::assertStringContainsString('2 total contact(s) to be added', $commandTester->getDisplay());

        $leadListTotalAfter = $leadListModel->getListLeadRepository()->findAll();
        self::assertCount(2, $leadListTotalAfter);
    }

    public function testUpdateLeadSegmentWithCompanySegmentEmpty(): void
    {
        $companyGlobo  = $this->addCompany('Globo', 'contact@globo.com');
        $companySbt    = $this->addCompany('SBT', 'contact@sbt.com');
        $companyRecord = $this->addCompany('Record', 'contact@record.com');

        $leadOne   = $this->createLead('John Globo Doe', 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', 'leadthree@mautic.com');
        $leadFour  = $this->createLead('Braw Doe', 'leadfour@mautic.com');

        $companyLeadGloboLeadOne = $this->addLeadToCompany($companyGlobo, $leadOne);
        $companyLeadGloboLeadTwo = $this->addLeadToCompany($companyGlobo, $leadTwo);
        $companyLeadSbtLeadThree = $this->addLeadToCompany($companySbt, $leadThree);
        $companyLeadSbtLeadFour  = $this->addLeadToCompany($companySbt, $leadFour);

        $totalCompanyLeadsBefore = $this->em->getRepository(CompanyLead::class)->findAll();
        self::assertCount(4, $totalCompanyLeadsBefore);
        $companySegmentOne             = $this->createCompanySegment('Test Company Segment 1', 'test_comp_segment');
        $companiesSegmentsOne          = $this->addCompanyToSegments($companyGlobo, $companySegmentOne);
        $resultCompaniesSegmentsBefore = $this->em->getRepository(CompaniesSegments::class)->findAll();
        self::assertCount(1, $resultCompaniesSegmentsBefore);
        $filtersToLeadSegment = [
            [
                'glue'       => 'and',
                'operator'   => 'empty',
                'field'      => 'company_segments',
                'type'       => 'company_segments',
                'object'     => 'company_segments',
            ],
        ];
        // Start Lead Segments
        $leadSegmentOne                = $this->createLeadSegment('Test Segment 1', 'test_segment', true, $filtersToLeadSegment);
        $leadListModel                 = static::getContainer()->get('mautic.lead.model.list');
        assert($leadListModel instanceof \Mautic\LeadBundle\Model\ListModel);
        // Get total of lead in list ( segments )
        $leadListTotalBefore = $leadListModel->getListLeadRepository()->findAll();
        // result zero because was add in $leadSegmentOne
        self::assertCount(0, $leadListTotalBefore);
        // COMMAND MAUTIC SEG UPDATE
        $kernel        = static::getContainer()->get('kernel');
        assert($kernel instanceof \Symfony\Component\HttpKernel\KernelInterface);
        $application   = new Application($kernel);
        $application->setAutoExit(false);
        $command       = $application->find('mautic:segments:update');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        self::assertStringContainsString('2 total contact(s) to be added', $commandTester->getDisplay());
        $leadListTotalAfter = $leadListModel->getListLeadRepository()->findAll();
        self::assertCount(2, $leadListTotalAfter);
    }

    public function testUpdateLeadSegmentsWithContactsWithAllContactsInAnyCompanySegment(): void
    {
        $companyGlobo  = $this->addCompany('Globo', 'contact@globo.com');
        $companySbt    = $this->addCompany('SBT', 'contact@sbt.com');
        $companyRecord = $this->addCompany('Record', 'contact@record.com');

        $leadOne   = $this->createLead('John Globo Doe', 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', 'leadthree@mautic.com');
        $leadFour  = $this->createLead('Braw Doe', 'leadfour@mautic.com');

        $companyLeadGloboLeadOne = $this->addLeadToCompany($companyGlobo, $leadOne);
        $companyLeadSbtLeadThree = $this->addLeadToCompany($companySbt, $leadThree);
        $companyLeadSbtLeadFour  = $this->addLeadToCompany($companySbt, $leadFour);

        $totalCompanyLeadsBefore = $this->em->getRepository(CompanyLead::class)->findAll();
        self::assertCount(3, $totalCompanyLeadsBefore);

        $companySegmentGlobo     = $this->createCompanySegment('Test Company Segment globo', 'test_comp_segment_globo');
        $companySegmentSbt       = $this->createCompanySegment('Test Company Segment Sbt', 'test_comp_segment_sbt');
        $companySegmentRecord    = $this->createCompanySegment('Test Company Segment Record', 'test_comp_segment_record');

        // globo added in Company Segment 1
        $companiesSegmentsGlobo  = $this->addCompanyToSegments($companyGlobo, $companySegmentGlobo);
        $companiesSegmentsSbt    = $this->addCompanyToSegments($companySbt, $companySegmentSbt);
        $companiesSegmentsRecord = $this->addCompanyToSegments($companyRecord, $companySegmentRecord);

        $filtersToLeadSegment = [
            [
                'glue'       => 'and',
                'operator'   => '!empty',
                'field'      => 'company_segments',
                'type'       => 'company_segments',
                'object'     => 'company_segments',
            ],
        ];

        $leadSegmentTwo = $this->createLeadSegment('Test Segment all not empty', 'test_segment_all_not_empty', true, $filtersToLeadSegment);

        // COMMAND MAUTIC SEG UPDATE
        $kernel        = static::getContainer()->get('kernel');
        assert($kernel instanceof \Symfony\Component\HttpKernel\KernelInterface);
        $application   = new Application($kernel);
        $application->setAutoExit(false);
        $command       = $application->find('mautic:segments:update');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        self::assertStringContainsString('3 total contact(s) to be added', $commandTester->getDisplay());

        $leadListModel = static::getContainer()->get('mautic.lead.model.list');
        assert($leadListModel instanceof \Mautic\LeadBundle\Model\ListModel);
        $leadListTotalAfter = $leadListModel->getListLeadRepository()->findAll();
        self::assertCount(3, $leadListTotalAfter);
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
    private function createLeadSegment(string $name, string $alias, bool $isPublished = true, array $filters = []): LeadList
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

    /**
     * @param array<array<mixed>> $filters
     */
    private function createCompanySegment(string $name, string $alias, bool $isPublished = true, array $filters = []): CompanySegment
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

    private function addLeadToCompany(Company $company, Lead $lead, bool $isPrimary = true): CompanyLead
    {
        $companyLead = new CompanyLead();
        $companyLead->setCompany($company);
        $companyLead->setLead($lead);
        $companyLead->setPrimary($isPrimary);
        $companyLead->setDateAdded(new \DateTime());
        $this->em->persist($companyLead);
        $this->em->flush();

        return $companyLead;
    }
}
