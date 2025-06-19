<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Controller\CompanyTestEntitiesTrait;

class AddRemoveCompanyEventLogSubscriberTest extends MauticMysqlTestCase
{
    use CompanyTestEntitiesTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    public function testAddRemoveCompanyEventLogSubscriber(): void
    {
        $lead1 = $this->createLead('test1@mautic.com', 'User 1');
        $lead2 = $this->createLead('test2@mautic.com', 'User 2');

        $company1 = $this->createCompany('Test Company');
        $company2 = $this->createCompany('Test Company 2');
        $this->addLeadToCompany($lead1, $company1);
        $this->addLeadToCompany($lead1, $company1);
        $this->addLeadToCompany($lead2, $company2);
        $this->addLeadToCompany($lead2, $company2);

        $companySegment = $this->createCompanySegment('Test Company Segment', 'test-company-segment');

        $companyEventLogModel = self::getContainer()->get('mautic.company_segments.model.company_event_log');
        assert($companyEventLogModel instanceof \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel);
        $allResults = $companyEventLogModel->getRepository()->findAll();
        self::assertCount(0, $allResults);

        $companySegmentModel = self::getContainer()->get('mautic.company_segments.model.company_segment');
        assert($companySegmentModel instanceof CompanySegmentModel);
        $companySegmentModel->addCompany($company1, [$companySegment]);
        $companySegmentModel->addCompany($company2, [$companySegment]);
        $allResults = $companyEventLogModel->getRepository()->findAll();
        self::assertCount(2, $allResults);
    }
}
