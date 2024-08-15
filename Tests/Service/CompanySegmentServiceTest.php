<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Service;

use Mautic\LeadBundle\DataFixtures\ORM\LoadCompanyData;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\UserBundle\DataFixtures\ORM\LoadRoleData;
use Mautic\UserBundle\DataFixtures\ORM\LoadUserData;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Command\UpdateCompanySegmentsCommand;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\DataFixtures\ORM\LoadCompanySegmentData;
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

        $companySegmentManual = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_1);
        $company1             = $this->getCompany('company-1');
        $companySegmentManual->addCompany($company1);
        $company2 = $this->getCompany('company-2');
        $companySegmentManual->addCompany($company2);

        $companySegmentModel = static::getContainer()->get(CompanySegmentModel::class);
        \assert($companySegmentModel instanceof CompanySegmentModel);
        $companySegmentModel->saveEntity($companySegmentManual);
        self::assertCount(2, $companySegmentManual->getCompanies());

        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_2);
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
        self::assertCount(0, $companySegment->getCompanies());

        $company1->addUpdatedField($filterField, '123456');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company1);

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(1, $companySegment->getCompanies());
        self::assertSame($company1, $companySegment->getCompanies()->get(0));

        $companySegmentManual = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_1);
        self::assertCount(2, $companySegmentManual->getCompanies(), 'The manual segment has still 2 Companies.');

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

        $companySegmentManual = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_1);
        $company1             = $this->getCompany('company-1');
        $companySegmentManual->addCompany($company1);
        $company2 = $this->getCompany('company-2');
        $companySegmentManual->addCompany($company2);

        $companySegmentModel = static::getContainer()->get(CompanySegmentModel::class);
        \assert($companySegmentModel instanceof CompanySegmentModel);
        $companySegmentModel->saveEntity($companySegmentManual);
        self::assertCount(2, $companySegmentManual->getCompanies());

        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_3);
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
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(2, $companySegment->getCompanies());

        // check 2 companies are added to dependent segment, and manual segment is also updated.
        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($companySegmentManualName, $rows->eq(0)->filter('td')->eq(1)->text());
        self::assertStringContainsString('2 Companies', $rows->eq(0)->filter('td')->eq(2)->text());
        self::assertStringContainsString($companySegmentName, $rows->eq(2)->filter('td')->eq(1)->text());
        self::assertStringContainsString('2 Companies', $rows->eq(2)->filter('td')->eq(2)->text());

        // remove one of companies
        $companySegmentManual = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_1);
        $company1             = $this->getCompany('company-1');
        $companySegmentManual->removeCompany($company1);
        $companySegmentModel->saveEntity($companySegmentManual);
        self::assertCount(1, $companySegmentManual->getCompanies());

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(1, $companySegment->getCompanies());
        self::assertSame($company2, $companySegment->getCompanies()->get(1));

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
        $companySegmentWithFilter = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_2);
        $existingFilters          = $companySegmentWithFilter->getFilters();
        self::assertCount(1, $existingFilters, 'Self-check.');
        $existingFilters[0]['field'] = $filterField;
        $companySegmentWithFilter->setFilters($existingFilters);
        $companySegmentModel->saveEntity($companySegmentWithFilter);

        // set dependency on the $companySegmentWithFilter
        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_3);
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
        self::assertCount(0, $companySegment->getCompanies());

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
        self::assertCount(1, $companySegment->getCompanies());
        self::assertSame($company1, $companySegment->getCompanies()->get(0));

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
        self::assertCount(1, $companySegment->getCompanies());
        self::assertSame($company2, $companySegment->getCompanies()->get(1));

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
        self::assertCount(0, $companySegment->getCompanies());

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

        $companySegmentManual = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_1);
        $company1             = $this->getCompany('company-1');
        $companySegmentManual->addCompany($company1);
        $company2 = $this->getCompany('company-2');
        $companySegmentManual->addCompany($company2);

        $companySegmentModel = static::getContainer()->get(CompanySegmentModel::class);
        \assert($companySegmentModel instanceof CompanySegmentModel);
        $companySegmentModel->saveEntity($companySegmentManual);
        self::assertCount(2, $companySegmentManual->getCompanies());

        $companySegment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_3);
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
        self::assertCount(0, $companySegment->getCompanies());

        // Now change the filter that's responsible for adding company to segment1
        $company1 = $this->getCompany('company-1');
        $company1->addUpdatedField($filterField, '123');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company1);

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentsCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(1, $companySegment->getCompanies());
        self::assertSame($company1, $companySegment->getCompanies()->get(0));

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

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentsCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(1, $companySegment->getCompanies());
        self::assertSame($company2, $companySegment->getCompanies()->get(1));

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

        $commandResult = $this->testSymfonyCommand($updateCompanySegmentsCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());
        self::assertCount(0, $companySegment->getCompanies());

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
}
