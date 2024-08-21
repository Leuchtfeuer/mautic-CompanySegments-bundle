<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Service;

use Mautic\LeadBundle\DataFixtures\ORM\LoadCompanyData;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\UserBundle\DataFixtures\ORM\LoadRoleData;
use Mautic\UserBundle\DataFixtures\ORM\LoadUserData;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Command\UpdateCompanySegmentsCommand;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\DataFixtures\ORM\LoadCompanySegmentData;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegmentsRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Helper\SegmentCountCacheHelper;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentActionModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Service\CompanySegmentService;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;
use Symfony\Component\HttpFoundation\Request;

class CompanySegmentServiceTest extends MauticMysqlTestCase
{
    /**
     * When I create a Company Segment “test2” with filter “Annual Revenue greater than 100000" and run the console command, nothing changes.
     * When I now change the Annual Revenue of one company to 123456, and re-run the console command, that company gets added to the segment
     * Same for filters on score.
     *
     * @dataProvider provideFilterFields
     */
    public function testRunningCommandWithMatchingCompaniesChangesSegment(string $filterField): void
    {
        $this->loadFixtures([LoadCompanyData::class, LoadCompanySegmentData::class, LoadUserData::class, LoadRoleData::class]);

        $companySegmentManual = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_NO_FILTERS);
        $company1             = $this->getCompany('company-1');
        $this->addCompanyToSegment($company1, $companySegmentManual);
        $company2 = $this->getCompany('company-2');
        $this->addCompanyToSegment($company2, $companySegmentManual);

        $companySegmentModel = static::getContainer()->get(CompanySegmentModel::class);
        \assert($companySegmentModel instanceof CompanySegmentModel);
        $companySegmentModel->saveEntity($companySegmentManual);
        self::assertCount(2, $companySegmentManual->getCompaniesSegments());

        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        $existingFilters = $companySegment->getFilters();
        self::assertCount(1, $existingFilters, 'Self-check.');
        $existingFilters[0]['field'] = $filterField;
        $companySegment->setFilters($existingFilters);
        $companySegmentModel->saveEntity($companySegment);

        $updateCompanySegmentCommandName = UpdateCompanySegmentsCommand::getDefaultName();
        self::assertNotNull($updateCompanySegmentCommandName);
        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(0, $companySegment->getCompaniesSegments());

        $company1->addUpdatedField($filterField, '123456');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company1);

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);

        $this->em->clear();
        $companySegment = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        $company1       = $this->getCompany('company-1');

        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(1, $companySegment->getCompaniesSegments());
        $companiesSegments = $companySegment->getCompaniesSegments()->get(0);
        self::assertNotNull($companiesSegments);
        self::assertFalse($companiesSegments->isManuallyAdded());
        self::assertFalse($companiesSegments->isManuallyRemoved());
        self::assertSame($company1, $companiesSegments->getCompany());

        $companySegmentManual = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_NO_FILTERS);
        self::assertCount(2, $companySegmentManual->getCompaniesSegments(), 'The manual segment has still 2 Companies.');

        $companySegmentService = self::getContainer()->get(CompanySegmentService::class);
        \assert($companySegmentService instanceof CompanySegmentService);
        self::assertSame('1', $companySegmentService->getTotalCompanySegmentsCompaniesCount($companySegment)[$companySegment->getId()]['count']);
    }

    public static function provideFilterFields(): \Generator
    {
        yield 'Company revenue' => ['companyannual_revenue'];
        yield 'Company score' => ['score'];
    }

    /**
     * When I create a Company Segment “test3” with filter “Company Segment Membership includes test1" and run the console command, both companies are added to test3.
     * When I remove one company from “test1” and re-run the console command, it is also removed from test3. Nothing else changes.
     * Check companies count changes accordingly.
     */
    public function testRunningCommandWithDependentSegmentChangesCompaniesInSegment(): void
    {
        $this->loadFixtures([LoadCompanyData::class, LoadCompanySegmentData::class, LoadUserData::class, LoadRoleData::class]);

        $companySegmentManual = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_NO_FILTERS);
        $company1             = $this->getCompany('company-1');
        $this->addCompanyToSegment($company1, $companySegmentManual);
        $company2 = $this->getCompany('company-2');
        $this->addCompanyToSegment($company2, $companySegmentManual);

        $companySegmentModel = static::getContainer()->get(CompanySegmentModel::class);
        \assert($companySegmentModel instanceof CompanySegmentModel);
        $companySegmentModel->saveEntity($companySegmentManual);
        self::assertCount(2, $companySegmentManual->getCompaniesSegments());

        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_DEPENDENT);
        $existingFilters = $companySegment->getFilters();
        self::assertCount(1, $existingFilters, 'Self-check.');
        self::assertIsArray($existingFilters[0], 'Self-check.');
        self::assertSame(CompanySegmentModel::PROPERTIES_FIELD, $existingFilters[0]['field'], 'Self-check.');

        // Check initial state. Before command is executed, there are no companies in cache.
        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        $companySegmentManualName = $companySegmentManual->getName();
        self::assertNotNull($companySegmentManualName);
        self::assertStringContainsString($companySegmentManualName, $rows->eq(0)->filter('td')->eq(1)->text());
        self::assertStringContainsString('No Companies', $rows->eq(0)->filter('td')->eq(2)->text());
        $companySegmentName = $companySegment->getName();
        self::assertNotNull($companySegmentName);
        self::assertStringContainsString($companySegmentName, $rows->eq(2)->filter('td')->eq(1)->text());
        self::assertStringContainsString('No Companies', $rows->eq(2)->filter('td')->eq(2)->text());

        $updateCompanySegmentCommandName = UpdateCompanySegmentsCommand::getDefaultName();
        self::assertNotNull($updateCompanySegmentCommandName);
        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);

        $this->em->clear();
        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_DEPENDENT);

        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(2, $companySegment->getCompaniesSegments());

        // check 2 companies are added to dependent segment, and manual segment is also updated.
        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($companySegmentManualName, $rows->eq(0)->filter('td')->eq(1)->text());
        self::assertStringContainsString('2 Companies', $rows->eq(0)->filter('td')->eq(2)->text());
        self::assertStringContainsString($companySegmentName, $rows->eq(2)->filter('td')->eq(1)->text());
        self::assertStringContainsString('2 Companies', $rows->eq(2)->filter('td')->eq(2)->text());

        $this->em->clear();

        // remove one of companies
        $companySegmentManual = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_NO_FILTERS);
        $company1             = $this->getCompany('company-1');
        $companySegmentModel->removeCompany($company1, [$companySegmentManual], true);
        self::assertCount(1, $companySegmentManual->getCompaniesSegments()->filter(static function (CompaniesSegments $companiesSegments) {
            return !$companiesSegments->isManuallyRemoved();
        }));

        $this->em->clear();

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());

        $this->em->clear();
        $companySegment = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_DEPENDENT);
        $company2       = $this->getCompany('company-2');

        self::assertCount(1, $companySegment->getCompaniesSegments());
        $companiesSegments = $companySegment->getCompaniesSegments()->get(0);
        self::assertNotNull($companiesSegments);
        self::assertSame($company2, $companiesSegments->getCompany());

        // check only one company are added to dependent segment
        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($companySegmentManualName, $rows->eq(0)->filter('td')->eq(1)->text());
        self::assertStringContainsString('1 Company', $rows->eq(0)->filter('td')->eq(2)->text());
        self::assertStringContainsString($companySegmentName, $rows->eq(2)->filter('td')->eq(1)->text());
        self::assertStringContainsString('1 Company', $rows->eq(2)->filter('td')->eq(2)->text());

        $companySegmentService = self::getContainer()->get(CompanySegmentService::class);
        \assert($companySegmentService instanceof CompanySegmentService);
        self::assertSame('1', $companySegmentService->getTotalCompanySegmentsCompaniesCount($companySegment)[$companySegment->getId()]['count']);
    }

    /**
     * Test if segment1 has filters, and segment2 has only "depends on segment1", then both segments will contain
     * same companies.
     *
     * @dataProvider provideFilterFields
     */
    public function testRunningCommandWithDependentSegmentChangesCompaniesInSegmentWithFilters(string $filterField): void
    {
        $this->loadFixtures([LoadCompanyData::class, LoadCompanySegmentData::class, LoadUserData::class, LoadRoleData::class]);

        $companySegmentModel = static::getContainer()->get(CompanySegmentModel::class);
        \assert($companySegmentModel instanceof CompanySegmentModel);

        // set filter type
        $companySegmentWithFilter = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        $existingFilters          = $companySegmentWithFilter->getFilters();
        self::assertCount(1, $existingFilters, 'Self-check.');
        $existingFilters[0]['field'] = $filterField;
        $companySegmentWithFilter->setFilters($existingFilters);
        $companySegmentModel->saveEntity($companySegmentWithFilter);

        // set dependency on the $companySegmentWithFilter
        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_DEPENDENT);
        $existingFilters = $companySegment->getFilters();
        self::assertCount(1, $existingFilters, 'Self-check.');
        self::assertIsArray($existingFilters[0], 'Self-check.');
        self::assertSame(CompanySegmentModel::PROPERTIES_FIELD, $existingFilters[0]['field'], 'Self-check.');
        $existingFilters[0]['properties']['filter'][0] = ''.$companySegmentWithFilter->getId();
        $companySegment->setFilters($existingFilters);
        $companySegmentModel->saveEntity($companySegment);

        // Check initial state.
        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        $companySegmentWithFilterName = $companySegmentWithFilter->getName();
        self::assertNotNull($companySegmentWithFilterName);
        self::assertStringContainsString($companySegmentWithFilterName, $rows->eq(1)->filter('td')->eq(1)->text());
        self::assertStringContainsString('No Companies', $rows->eq(1)->filter('td')->eq(2)->text());
        $companySegmentName = $companySegment->getName();
        self::assertNotNull($companySegmentName);
        self::assertStringContainsString($companySegmentName, $rows->eq(2)->filter('td')->eq(1)->text());
        self::assertStringContainsString('No Companies', $rows->eq(2)->filter('td')->eq(2)->text());

        $updateCompanySegmentCommandName = UpdateCompanySegmentsCommand::getDefaultName();
        self::assertNotNull($updateCompanySegmentCommandName);
        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(0, $companySegment->getCompaniesSegments());

        // Now change the filter that's responsible for adding company to segment1
        $company1 = $this->getCompany('company-1');
        $company1->addUpdatedField($filterField, '123456');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company1);

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());

        $this->em->clear();
        $companySegment = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_DEPENDENT);
        $company1       = $this->getCompany('company-1');

        self::assertCount(1, $companySegment->getCompaniesSegments());
        $companiesSegments = $companySegment->getCompaniesSegments()->get(0);
        self::assertNotNull($companiesSegments);
        self::assertFalse($companiesSegments->isManuallyAdded());
        self::assertFalse($companiesSegments->isManuallyRemoved());
        self::assertSame($company1, $companiesSegments->getCompany());

        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($companySegmentWithFilterName, $rows->eq(1)->filter('td')->eq(1)->text());
        self::assertStringContainsString('1 Company', $rows->eq(1)->filter('td')->eq(2)->text());
        self::assertStringContainsString($companySegmentName, $rows->eq(2)->filter('td')->eq(1)->text());
        self::assertStringContainsString('1 Company', $rows->eq(2)->filter('td')->eq(2)->text());

        // now change company2 to conform to filters and execute query.
        $company1 = $this->getCompany('company-1');
        $company1->addUpdatedField($filterField, '1');
        $company2 = $this->getCompany('company-2');
        $company2->addUpdatedField($filterField, '123456');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company1);
        $companyModel->saveEntity($company2);

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());

        $this->em->clear();
        $companySegment = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_DEPENDENT);
        $company2       = $this->getCompany('company-2');

        self::assertCount(1, $companySegment->getCompaniesSegments());
        $companiesSegments = $companySegment->getCompaniesSegments()->get(0);
        self::assertNotNull($companiesSegments);
        self::assertSame($company2, $companiesSegments->getCompany());

        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($companySegmentWithFilterName, $rows->eq(1)->filter('td')->eq(1)->text());
        self::assertStringContainsString('1 Company', $rows->eq(1)->filter('td')->eq(2)->text());
        self::assertStringContainsString($companySegmentName, $rows->eq(2)->filter('td')->eq(1)->text());
        self::assertStringContainsString('1 Company', $rows->eq(2)->filter('td')->eq(2)->text());

        // remove conforming filter from company2
        $company2 = $this->getCompany('company-2');
        $company2->addUpdatedField($filterField, '3');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company2);

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(0, $companySegment->getCompaniesSegments());

        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($companySegmentWithFilterName, $rows->eq(1)->filter('td')->eq(1)->text());
        self::assertStringContainsString('No Companies', $rows->eq(1)->filter('td')->eq(2)->text());
        self::assertStringContainsString($companySegmentName, $rows->eq(2)->filter('td')->eq(1)->text());
        self::assertStringContainsString('No Companies', $rows->eq(2)->filter('td')->eq(2)->text());

        $companySegmentService = self::getContainer()->get(CompanySegmentService::class);
        \assert($companySegmentService instanceof CompanySegmentService);
        self::assertSame('0', $companySegmentService->getTotalCompanySegmentsCompaniesCount($companySegmentWithFilter)[$companySegmentWithFilter->getId()]['count']);
    }

    /**
     * Test if segment1 has filters, and another segment has "depends on segment1" and other filter (a different one),
     * then segments will contain companies that are conforming to segment from segment1 and a "different" filter.
     *
     * @dataProvider provideFilterFields
     */
    public function testRunningCommandWithDependentWithFiltersSegmentChangesCompaniesInSegment(string $filterField): void
    {
        $this->loadFixtures([LoadCompanyData::class, LoadCompanySegmentData::class, LoadUserData::class, LoadRoleData::class]);

        $companySegmentManual = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_NO_FILTERS);
        $company1             = $this->getCompany('company-1');
        $this->addCompanyToSegment($company1, $companySegmentManual);
        $company2 = $this->getCompany('company-2');
        $this->addCompanyToSegment($company2, $companySegmentManual);

        $companySegmentModel = static::getContainer()->get(CompanySegmentModel::class);
        \assert($companySegmentModel instanceof CompanySegmentModel);
        $companySegmentModel->saveEntity($companySegmentManual);
        self::assertCount(2, $companySegmentManual->getCompaniesSegments());

        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_DEPENDENT);
        $existingFilters = $companySegment->getFilters();
        self::assertCount(1, $existingFilters, 'Self-check.');
        $existingFilters[1] = [
            'glue'       => 'and',
            'operator'   => 'gte',
            'properties' => [
                'filter' => '100', // should be lower than fixtures LoadCompanySegmentData::COMPANY_SEGMENT_2
            ],
            'field'    => $filterField,
            'type'     => 'number',
            'object'   => 'company',
            'display'  => '',
        ];
        $companySegment->setFilters($existingFilters);
        $companySegmentModel->saveEntity($companySegment);

        // Check initial state.
        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        $companySegmentManualName = $companySegmentManual->getName();
        self::assertNotNull($companySegmentManualName);
        self::assertStringContainsString($companySegmentManualName, $rows->eq(0)->filter('td')->eq(1)->text());
        self::assertStringContainsString('No Companies', $rows->eq(0)->filter('td')->eq(2)->text());
        $companySegmentName = $companySegment->getName();
        self::assertNotNull($companySegmentName);
        self::assertStringContainsString($companySegmentName, $rows->eq(2)->filter('td')->eq(1)->text());
        self::assertStringContainsString('No Companies', $rows->eq(2)->filter('td')->eq(2)->text());

        $updateCompanySegmentsCommandName = UpdateCompanySegmentsCommand::getDefaultName();
        self::assertNotNull($updateCompanySegmentsCommandName);
        $commandResult = $this->testSymfonyCommand($updateCompanySegmentsCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(0, $companySegment->getCompaniesSegments());

        // Now change the filter that's responsible for adding company to segment1
        $company1 = $this->getCompany('company-1');
        $company1->addUpdatedField($filterField, '123');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company1);

        $this->em->clear();

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentsCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());

        // clear manager to check for relations
        $this->em->clear();
        $company1        = $this->getCompany('company-1');
        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_DEPENDENT);

        self::assertCount(1, $companySegment->getCompaniesSegments());
        $companiesSegments = $companySegment->getCompaniesSegments()->get(0);
        self::assertNotNull($companiesSegments);
        self::assertFalse($companiesSegments->isManuallyAdded());
        self::assertFalse($companiesSegments->isManuallyRemoved());
        self::assertSame($company1, $companiesSegments->getCompany());

        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($companySegmentManualName, $rows->eq(0)->filter('td')->eq(1)->text());
        self::assertStringContainsString('2 Companies', $rows->eq(0)->filter('td')->eq(2)->text());
        self::assertStringContainsString($companySegmentName, $rows->eq(2)->filter('td')->eq(1)->text());
        self::assertStringContainsString('1 Company', $rows->eq(2)->filter('td')->eq(2)->text());

        $companySegmentService = self::getContainer()->get(CompanySegmentService::class);
        \assert($companySegmentService instanceof CompanySegmentService);
        self::assertSame('1', $companySegmentService->getTotalCompanySegmentsCompaniesCount($companySegment)[$companySegment->getId()]['count']);

        // now change company2 to conform to filters and execute query.
        $company1 = $this->getCompany('company-1');
        $company1->addUpdatedField($filterField, '1');
        $company2 = $this->getCompany('company-2');
        $company2->addUpdatedField($filterField, '123');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company1);
        $companyModel->saveEntity($company2);

        $this->em->clear();

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentsCommandName, [
            '--env' => 'test',
        ]);

        // need to refetch the segment to get new relations.
        $this->em->clear();
        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_DEPENDENT);
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(1, $companySegment->getCompaniesSegments());
        $company2 = $this->getCompany('company-2');
        self::assertSame($company2, $companySegment->getCompaniesSegments()->get(0)?->getCompany());

        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($companySegmentManualName, $rows->eq(0)->filter('td')->eq(1)->text());
        self::assertStringContainsString('2 Companies', $rows->eq(0)->filter('td')->eq(2)->text());
        self::assertStringContainsString($companySegmentName, $rows->eq(2)->filter('td')->eq(1)->text());
        self::assertStringContainsString('1 Company', $rows->eq(2)->filter('td')->eq(2)->text());

        $companySegmentService = self::getContainer()->get(CompanySegmentService::class);
        \assert($companySegmentService instanceof CompanySegmentService);
        self::assertSame('1', $companySegmentService->getTotalCompanySegmentsCompaniesCount($companySegment)[$companySegment->getId()]['count']);

        // remove conforming filter from company2
        $company2 = $this->getCompany('company-2');
        $company2->addUpdatedField($filterField, '3');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company2);

        $this->em->clear();

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentsCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());

        $this->em->clear();

        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_DEPENDENT);
        self::assertCount(0, $companySegment->getCompaniesSegments());

        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($companySegmentManualName, $rows->eq(0)->filter('td')->eq(1)->text());
        self::assertStringContainsString('2 Companies', $rows->eq(0)->filter('td')->eq(2)->text());
        self::assertStringContainsString($companySegmentName, $rows->eq(2)->filter('td')->eq(1)->text());
        self::assertStringContainsString('No Companies', $rows->eq(2)->filter('td')->eq(2)->text());

        $companySegmentService = self::getContainer()->get(CompanySegmentService::class);
        \assert($companySegmentService instanceof CompanySegmentService);
        self::assertSame('0', $companySegmentService->getTotalCompanySegmentsCompaniesCount($companySegment)[$companySegment->getId()]['count']);
    }

    /**
     * When I create a Company Segment “test2” with filter “Annual Revenue greater than 100000",
     * add manually "Company 2", which *does not* match filters on segment, and then run command - the segment contains only "Company 2"
     * When I now change the Annual Revenue of one "Company 1" to 123456, and re-run the console command, that company gets added to the segment,
     * and "Company 2" will be still on the list.
     * Same for filters on score.
     *
     * @dataProvider provideFilterFields
     */
    public function testManuallyAddedCompanyIsNotRemoved(string $filterField): void
    {
        $this->loadFixtures([LoadCompanyData::class, LoadCompanySegmentData::class, LoadUserData::class, LoadRoleData::class]);

        $companySegmentModel = static::getContainer()->get(CompanySegmentModel::class);
        \assert($companySegmentModel instanceof CompanySegmentModel);

        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        $existingFilters = $companySegment->getFilters();
        self::assertCount(1, $existingFilters, 'Self-check.');
        $existingFilters[0]['field'] = $filterField;
        $companySegment->setFilters($existingFilters);
        $companySegmentModel->saveEntity($companySegment);

        $company2 = $this->getCompany('company-2');
        $this->addCompanyToSegment($company2, $companySegment);

        $updateCompanySegmentCommandName = UpdateCompanySegmentsCommand::getDefaultName();
        self::assertNotNull($updateCompanySegmentCommandName);
        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(1, $companySegment->getCompaniesSegments());

        $company1 = $this->getCompany('company-1');
        $company1->addUpdatedField($filterField, '123456');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company1);

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);

        $this->em->clear();
        $companySegment = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        self::assertNotNull($companySegment->getId());
        $company1 = $this->getCompany('company-1');
        $company2 = $this->getCompany('company-2');

        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(2, $companySegment->getCompaniesSegments());
        $companiesSegments = $companySegment->getCompaniesSegments()->get(0);
        self::assertNotNull($companiesSegments);
        self::assertFalse($companiesSegments->isManuallyAdded());
        self::assertFalse($companiesSegments->isManuallyRemoved());
        self::assertSame($company1, $companiesSegments->getCompany());

        $companiesSegments = $companySegment->getCompaniesSegments()->get(1);
        self::assertNotNull($companiesSegments);
        self::assertTrue($companiesSegments->isManuallyAdded());
        self::assertFalse($companiesSegments->isManuallyRemoved());
        self::assertSame($company2, $companiesSegments->getCompany());

        $segmentCountHelper = self::getContainer()->get(SegmentCountCacheHelper::class);
        \assert($segmentCountHelper instanceof SegmentCountCacheHelper);
        self::assertSame(2, $segmentCountHelper->getSegmentCompanyCount($companySegment->getId()));

        $companySegmentService = self::getContainer()->get(CompanySegmentService::class);
        \assert($companySegmentService instanceof CompanySegmentService);
        self::assertSame('2', $companySegmentService->getTotalCompanySegmentsCompaniesCount($companySegment)[$companySegment->getId()]['count']);

        // Check that company that was added is present in the list with the company-segment filter in companies view
        $crawler = $this->client->request(Request::METHOD_GET, '/s/companies?search=company-segment:'.$companySegment->getAlias());
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companyTable > tbody > tr');
        self::assertCount(2, $rows);
    }

    /**
     * When I create a Company Segment “test2” with filter “Annual Revenue greater than 100000",
     * remove manually "Company 2", which *does* match filters on segment, and then run command - the segment contains nothing
     * When I now change the Annual Revenue of one "Company 1" to 123456, and re-run the console command, that company gets added to the segment,
     * and that's all.
     * Same for filters on score.
     *
     * @dataProvider provideFilterFields
     */
    public function testManuallyRemovedCompanyIsNotAdded(string $filterField): void
    {
        $this->loadFixtures([LoadCompanyData::class, LoadCompanySegmentData::class, LoadUserData::class, LoadRoleData::class]);

        $companySegmentModel = static::getContainer()->get(CompanySegmentModel::class);
        \assert($companySegmentModel instanceof CompanySegmentModel);

        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        $existingFilters = $companySegment->getFilters();
        self::assertCount(1, $existingFilters, 'Self-check.');
        $existingFilters[0]['field'] = $filterField;
        $companySegment->setFilters($existingFilters);
        $companySegmentModel->saveEntity($companySegment);

        $company2 = $this->getCompany('company-2');
        $this->addCompanyToSegment($company2, $companySegment, false, true);

        $updateCompanySegmentCommandName = UpdateCompanySegmentsCommand::getDefaultName();
        self::assertNotNull($updateCompanySegmentCommandName);
        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(0, $companySegment->getCompaniesSegments());

        $company1 = $this->getCompany('company-1');
        $company1->addUpdatedField($filterField, '123456');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company1);

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);

        $this->em->clear();
        $companySegment = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        self::assertNotNull($companySegment->getId());
        $company1 = $this->getCompany('company-1');

        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(1, $companySegment->getCompaniesSegments());
        $companiesSegments = $companySegment->getCompaniesSegments()->get(0);
        self::assertNotNull($companiesSegments);
        self::assertFalse($companiesSegments->isManuallyAdded());
        self::assertFalse($companiesSegments->isManuallyRemoved());
        self::assertSame($company1, $companiesSegments->getCompany());

        $segmentCountHelper = self::getContainer()->get(SegmentCountCacheHelper::class);
        \assert($segmentCountHelper instanceof SegmentCountCacheHelper);
        self::assertSame(1, $segmentCountHelper->getSegmentCompanyCount($companySegment->getId()));

        $companySegmentService = self::getContainer()->get(CompanySegmentService::class);
        \assert($companySegmentService instanceof CompanySegmentService);
        self::assertSame('1', $companySegmentService->getTotalCompanySegmentsCompaniesCount($companySegment)[$companySegment->getId()]['count']);

        // Check that company that was removed is not in the list with the company-segment filter in companies view
        $crawler = $this->client->request(Request::METHOD_GET, '/s/companies?search=company-segment:'.$companySegment->getAlias());
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companyTable > tbody > tr');
        self::assertCount(1, $rows);
    }

    /**
     * When I create a Company Segment “test2” with filter “Annual Revenue greater than 100000",
     * make sure only "Company 2" conforms the filter, then run the command.
     * The company is added to the segment, which is also visible on the companies filtered list.
     * Then i manually add the "Company 1" to the list, rerun the command and check that segment contains both
     * companies, and the companies filtered list also contains both.
     * Same for filters on score.
     *
     * @dataProvider provideFilterFields
     */
    public function testManuallyAddedIsVisibleInCompaniesView(string $filterField): void
    {
        $this->loadFixtures([LoadCompanyData::class, LoadCompanySegmentData::class, LoadUserData::class, LoadRoleData::class]);

        $companySegmentModel = static::getContainer()->get(CompanySegmentModel::class);
        \assert($companySegmentModel instanceof CompanySegmentModel);

        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        $existingFilters = $companySegment->getFilters();
        self::assertCount(1, $existingFilters, 'Self-check.');
        $existingFilters[0]['field'] = $filterField;
        $companySegment->setFilters($existingFilters);
        $companySegmentModel->saveEntity($companySegment);

        $company1 = $this->getCompany('company-1');
        $company1->addUpdatedField($filterField, '1');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company1);

        $company2 = $this->getCompany('company-2');
        $company2->addUpdatedField($filterField, '123456');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company2);

        $updateCompanySegmentCommandName = UpdateCompanySegmentsCommand::getDefaultName();
        self::assertNotNull($updateCompanySegmentCommandName);
        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(1, $companySegment->getCompaniesSegments());

        $this->em->clear();
        $companySegment = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        self::assertNotNull($companySegment->getId());
        $company2 = $this->getCompany('company-2');

        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(1, $companySegment->getCompaniesSegments());
        $companiesSegments = $companySegment->getCompaniesSegments()->get(0);
        self::assertNotNull($companiesSegments);
        self::assertFalse($companiesSegments->isManuallyAdded());
        self::assertFalse($companiesSegments->isManuallyRemoved());
        self::assertSame($company2, $companiesSegments->getCompany());

        $segmentCountHelper = self::getContainer()->get(SegmentCountCacheHelper::class);
        \assert($segmentCountHelper instanceof SegmentCountCacheHelper);
        self::assertSame(1, $segmentCountHelper->getSegmentCompanyCount($companySegment->getId()));

        $companySegmentService = self::getContainer()->get(CompanySegmentService::class);
        \assert($companySegmentService instanceof CompanySegmentService);
        self::assertSame('1', $companySegmentService->getTotalCompanySegmentsCompaniesCount($companySegment)[$companySegment->getId()]['count']);

        // Check that only one company appear in filtered companies list
        $crawler = $this->client->request(Request::METHOD_GET, '/s/companies?search=company-segment:'.$companySegment->getAlias());
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companyTable > tbody > tr');
        self::assertCount(1, $rows);

        // now manually add the company 1.
        $companySegmentActionModel = self::getContainer()->get(CompanySegmentActionModel::class);
        \assert($companySegmentActionModel instanceof CompanySegmentActionModel);
        $companySegmentActionModel->addCompanies([$company1->getId()], [$companySegment->getId()], true);

        // Check that both company appear in filtered companies list
        $crawler = $this->client->request(Request::METHOD_GET, '/s/companies?search=company-segment:'.$companySegment->getAlias());
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companyTable > tbody > tr');
        self::assertCount(2, $rows);
    }

    /**
     * When I create a Company Segment “test2” with filter “Annual Revenue greater than 100000",
     * make sure "Company 1" and "Company 2" conforms the filter, then run the command.
     * Both companies are added to the segment, which is also visible on the companies filtered list.
     * Then i manually remove the "Company 1" from the list, rerun the command and check that segment contains only one
     * company, and the companies filtered list also contains only one.
     * Same for filters on score.
     *
     * @dataProvider provideFilterFields
     */
    public function testManuallyRemovedNotVisibleInCompaniesView(string $filterField): void
    {
        $this->loadFixtures([LoadCompanyData::class, LoadCompanySegmentData::class, LoadUserData::class, LoadRoleData::class]);

        $companySegmentModel = static::getContainer()->get(CompanySegmentModel::class);
        \assert($companySegmentModel instanceof CompanySegmentModel);

        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        $existingFilters = $companySegment->getFilters();
        self::assertCount(1, $existingFilters, 'Self-check.');
        $existingFilters[0]['field'] = $filterField;
        $companySegment->setFilters($existingFilters);
        $companySegmentModel->saveEntity($companySegment);

        $company1 = $this->getCompany('company-1');
        $company1->addUpdatedField($filterField, '123456');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company1);

        $company2 = $this->getCompany('company-2');
        $company2->addUpdatedField($filterField, '123456');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company2);

        $updateCompanySegmentCommandName = UpdateCompanySegmentsCommand::getDefaultName();
        self::assertNotNull($updateCompanySegmentCommandName);
        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(2, $companySegment->getCompaniesSegments());

        $this->em->clear();
        $companySegment = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        self::assertNotNull($companySegment->getId());
        $company1 = $this->getCompany('company-1');
        $company2 = $this->getCompany('company-2');

        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(2, $companySegment->getCompaniesSegments());
        $companiesSegments = $companySegment->getCompaniesSegments()->get(0);
        self::assertNotNull($companiesSegments);
        self::assertFalse($companiesSegments->isManuallyAdded());
        self::assertFalse($companiesSegments->isManuallyRemoved());
        self::assertSame($company1, $companiesSegments->getCompany());
        $companiesSegments = $companySegment->getCompaniesSegments()->get(1);
        self::assertNotNull($companiesSegments);
        self::assertFalse($companiesSegments->isManuallyAdded());
        self::assertFalse($companiesSegments->isManuallyRemoved());
        self::assertSame($company2, $companiesSegments->getCompany());

        $segmentCountHelper = self::getContainer()->get(SegmentCountCacheHelper::class);
        \assert($segmentCountHelper instanceof SegmentCountCacheHelper);
        self::assertSame(2, $segmentCountHelper->getSegmentCompanyCount($companySegment->getId()));

        $companySegmentService = self::getContainer()->get(CompanySegmentService::class);
        \assert($companySegmentService instanceof CompanySegmentService);
        self::assertSame('2', $companySegmentService->getTotalCompanySegmentsCompaniesCount($companySegment)[$companySegment->getId()]['count']);

        // Check that both companies appear in filtered companies list
        $crawler = $this->client->request(Request::METHOD_GET, '/s/companies?search=company-segment:'.$companySegment->getAlias());
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companyTable > tbody > tr');
        self::assertCount(2, $rows);

        // now manually remove the company 1.
        $companySegmentActionModel = self::getContainer()->get(CompanySegmentActionModel::class);
        \assert($companySegmentActionModel instanceof CompanySegmentActionModel);
        $companySegmentActionModel->removeCompanies([$company1->getId()], [$companySegment->getId()], true);

        // Check that one company appear in filtered companies list
        $crawler = $this->client->request(Request::METHOD_GET, '/s/companies?search=company-segment:'.$companySegment->getAlias());
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companyTable > tbody > tr');
        self::assertCount(1, $rows);
    }

    private function addCompanyToSegment(Company $company, CompanySegment $companySegment, bool $manuallyAdded = true, bool $manuallyRemoved = false): void
    {
        $companiesSegments = new CompaniesSegments();
        $companiesSegments->setCompanySegment($companySegment);
        $companiesSegments->setCompany($company);
        $companiesSegments->setManuallyAdded($manuallyAdded);
        $companiesSegments->setManuallyRemoved($manuallyRemoved);
        $companiesSegments->setDateAdded(new \DateTime());
        $companySegment->addCompaniesSegment($companiesSegments);

        $companiesSegmentsRepository = static::getContainer()->get(CompaniesSegmentsRepository::class);
        \assert($companiesSegmentsRepository instanceof CompaniesSegmentsRepository);
        $companiesSegmentsRepository->saveEntity($companiesSegments);
    }
}
