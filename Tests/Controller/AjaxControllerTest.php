<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Controller;

use Mautic\LeadBundle\DataFixtures\ORM\LoadCompanyData;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Segment\OperatorOptions;
use Mautic\UserBundle\DataFixtures\ORM\LoadRoleData;
use Mautic\UserBundle\DataFixtures\ORM\LoadUserData;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Command\UpdateCompanySegmentsCommand;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\DataFixtures\ORM\LoadCompanySegmentData;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AjaxControllerTest extends MauticMysqlTestCase
{
    public function testCompanySegmentFilter(): void
    {
        $this->client->xmlHttpRequest(Request::METHOD_POST, '/s/ajax', [
            'action'      => 'plugin:LeuchtfeuerCompanySegments:loadCompanySegmentFilterForm',
            'fieldAlias'  => 'date_modified',
            'fieldObject' => 'company_segments',
            'operator'    => OperatorOptions::EQUAL_TO,
            'filterNum'   => '1',
        ], [], $this->createAjaxHeaders());

        $response = $this->client->getResponse();
        self::assertEquals(200, $response->getStatusCode());
        $data = $response->getContent();
        self::assertNotFalse($data);
        $json = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($json);
        self::assertArrayHasKey('viewParameters', $json);
        self::assertArrayHasKey('form', $json['viewParameters']);
        self::assertIsString($json['viewParameters']['form']);
        self::assertStringContainsString('company_segments_filters_1_properties_filter', $json['viewParameters']['form']);
        self::assertStringContainsString('name="company_segments[filters][1][properties][filter]"', $json['viewParameters']['form']);
    }

    public function testSegmentCount(): void
    {
        $this->loadFixtures([LoadCompanyData::class, LoadCompanySegmentData::class, LoadUserData::class, LoadRoleData::class]);

        $companySegmentManual = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_1);
        $company              = $this->getCompany('company-1');
        $companySegmentManual->addCompany($company);
        $company2 = $this->getCompany('company-2');
        $companySegmentManual->addCompany($company2);

        $companySegmentModel = static::getContainer()->get(CompanySegmentModel::class);
        \assert($companySegmentModel instanceof CompanySegmentModel);
        $companySegmentModel->saveEntity($companySegmentManual);
        self::assertCount(2, $companySegmentManual->getCompanies());

        $companySegmentFiltered = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_2);
        $company->addUpdatedField('companyannual_revenue', '123456');
        $companyModel = static::getContainer()->get(CompanyModel::class);
        \assert($companyModel instanceof CompanyModel);
        $companyModel->saveEntity($company);
        self::assertCount(0, $companySegmentFiltered->getCompanies(), 'Check that there are no aut-saving to the cache.');

        $companySegmentDependent = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_3);
        self::assertCount(0, $companySegmentDependent->getCompanies());

        // Though the DB contains "proper" counts of companies in segments, the command need to be executed to fill in the cache.
        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        $companySegmentManualName = $companySegmentManual->getName();
        self::assertNotNull($companySegmentManualName);
        self::assertStringContainsString($companySegmentManualName, $rows->eq(0)->filter('td')->eq(1)->text());
        self::assertStringContainsString('No Companies', $rows->eq(0)->filter('td')->eq(2)->text());
        $companySegmentFilteredName = $companySegmentFiltered->getName();
        self::assertNotNull($companySegmentFilteredName);
        self::assertStringContainsString($companySegmentFilteredName, $rows->eq(1)->filter('td')->eq(1)->text());
        self::assertStringContainsString('No Companies', $rows->eq(1)->filter('td')->eq(2)->text());
        $companySegmentDependentName = $companySegmentDependent->getName();
        self::assertNotNull($companySegmentDependentName);
        self::assertStringContainsString($companySegmentDependentName, $rows->eq(2)->filter('td')->eq(1)->text());
        self::assertStringContainsString('No Companies', $rows->eq(2)->filter('td')->eq(2)->text());

        // test ajax
        $this->checkGetCompaniesCountAjaxRequest($companySegmentManual, 'No Companies', 0);
        $this->checkGetCompaniesCountAjaxRequest($companySegmentFiltered, 'No Companies', 0);
        $this->checkGetCompaniesCountAjaxRequest($companySegmentDependent, 'No Companies', 0);

        $updateCompanySegmentCommandName = UpdateCompanySegmentsCommand::getDefaultName();
        self::assertNotNull($updateCompanySegmentCommandName);
        $commandResult = $this->testSymfonyCommand($updateCompanySegmentCommandName, [
            '--env' => 'test',
        ]);
        self::assertSame(0, $commandResult->getStatusCode());

        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($companySegmentManualName, $rows->eq(0)->filter('td')->eq(1)->text());
        self::assertStringContainsString('2 Companies', $rows->eq(0)->filter('td')->eq(2)->text());
        self::assertStringContainsString($companySegmentFilteredName, $rows->eq(1)->filter('td')->eq(1)->text());
        self::assertStringContainsString('1 Company', $rows->eq(1)->filter('td')->eq(2)->text());
        self::assertStringContainsString($companySegmentDependentName, $rows->eq(2)->filter('td')->eq(1)->text());
        self::assertStringContainsString('2 Companies', $rows->eq(2)->filter('td')->eq(2)->text());

        // test ajax
        $this->checkGetCompaniesCountAjaxRequest($companySegmentManual, 'View 2 Companies', 2);
        $this->checkGetCompaniesCountAjaxRequest($companySegmentFiltered, 'View 1 Company', 1);
        $this->checkGetCompaniesCountAjaxRequest($companySegmentDependent, 'View 2 Companies', 2);
    }

    private function checkGetCompaniesCountAjaxRequest(CompanySegment $companySegment, string $html, int $companiesCount): void
    {
        $parameter = ['id' => $companySegment->getId()];
        $this->client->request(Request::METHOD_GET, '/s/ajax?action=plugin:LeuchtfeuerCompanySegments:getCompaniesCount', $parameter);
        $clientResponse = $this->client->getResponse();

        self::assertSame(Response::HTTP_OK, $clientResponse->getStatusCode());

        $content = json_decode((string) $clientResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($content);
        self::assertArrayHasKey('html', $content);
        self::assertArrayHasKey('companyCount', $content);

        self::assertSame($html, $content['html']);
        self::assertSame($companiesCount, $content['companyCount']);
    }
}
