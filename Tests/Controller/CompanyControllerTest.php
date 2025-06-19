<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;

class CompanyControllerTest extends MauticMysqlTestCase
{
    use CompanyTestEntitiesTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin(true);
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    public function testViewActionCompanyNotFound(): void
    {
        // Use a non-existent company ID
        $this->client->request('GET', '/s/companies/view/999999');

        $response = $this->client->getResponse();

        self::assertStringContainsString('Company not found.', $response->getContent());
    }

    public function testViewActionShowsCompanyAndLeads(): void
    {
        // Create leads
        $lead1 = $this->createLead('alice@example.com', 'Alice');
        $lead2 = $this->createLead('bob@example.com', 'Bob');

        // Create company
        $company = $this->createCompany('TestCompany');

        // Add leads to company
        $this->addLeadToCompany($lead1, $company);
        $this->addLeadToCompany($lead2, $company);

        // Request the company view page
        $this->client->request('GET', '/s/companies/view/'.$company->getId());
        $response = $this->client->getResponse();

        // Assert company name and lead names are present in the response
        self::assertStringContainsString('TestCompany', $response->getContent());
        self::assertStringContainsString('Alice', $response->getContent());
        self::assertStringContainsString('Bob', $response->getContent());
    }
}
