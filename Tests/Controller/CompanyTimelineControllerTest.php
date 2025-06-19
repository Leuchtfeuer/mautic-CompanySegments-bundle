<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Controller;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\Lead as CampaignMember;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Tests\Functional\CreateTestEntitiesTrait;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;

class CompanyTimelineControllerTest extends MauticMysqlTestCase
{
    use CreateTestEntitiesTrait;
    use CompanyTestEntitiesTrait;
    private const SALES_USER = 'sales';

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin(true);
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    public function testIndexActionsIsSuccessful(): void
    {
        $company = $this->createCompany('TestCompany');
        $this->em->flush();

        $this->client->request('GET', '/s/companies/timeline/'.$company->getId());
        //        dd($this->client->getResponse()->getContent());
        self::assertEquals(200, $this->client->getResponse()->getStatusCode());
    }

    public function testIndexActionsWithNoCompanyId(): void
    {
        $this->client->request('GET', '/s/companies/timeline/');
        self::assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testIndexActionsWithInvalidCompanyId(): void
    {
        $this->client->request('GET', '/s/companies/timeline/999999');
        self::assertEquals(404, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('Company event log not found for company', $this->client->getResponse()->getContent());
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function createEventModifyCompanySegment(
        string $name,
        string $type,
        array $properties = [],
        string $eventType = 'action',
        int $order =1,
        string $anchor = '',
        ?Event $parent = null,
    ): Event {
        $event = new Event();
        $event->setOrder($order);
        $event->setName($name);
        $event->setType($type);
        $event->setEventType($eventType);
        $event->setProperties($properties);
        if ('' !== $anchor) {
            $event->setDecisionPath($anchor);
        }
        if (null !== $parent) {
            $event->setParent($parent);
        }

        return $event;
    }

    private function addLeadInCampaign(Campaign $campaign, Lead $lead): CampaignMember
    {
        $campaignMember = new CampaignMember();
        $campaignMember->setLead($lead);
        $campaignMember->setCampaign($campaign);
        $campaignMember->setDateAdded(new \DateTime('-61 seconds'));

        return $campaignMember;
    }

    private function createLead(string $email, string $name='Joe'): Lead
    {
        $lead = new Lead();
        $lead->setFirstname($name);
        $lead->setEmail($email);
        $lead->setDateAdded(new \DateTime());
        $lead->setDateModified(new \DateTime());

        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }

    private function createCompany(string $companyName = 'Mauticcomp'): Company
    {
        $company = new Company();
        $company->setName($companyName);
        $company->setDateAdded(new \DateTime());
        $company->setDateModified(new \DateTime());

        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }

    private function createCompanySegment(string $name = 'Segment test', string $alias = 'segment-test', bool $isPublished = true): CompanySegment
    {
        $companySegment = new CompanySegment();
        $companySegment->setName($name);
        $companySegment->setAlias($alias);
        $companySegment->setIsPublished($isPublished);
        $companySegment->setDateAdded(new \DateTime());
        $companySegment->setDateModified(new \DateTime());

        $this->em->persist($companySegment);
        $this->em->flush();

        return $companySegment;
    }

    private function addLeadToCompany(Lead $lead, Company $company, bool $isPrimary = false): void
    {
        $lead->setPrimaryCompany($company);
        $lead->setDateAdded(new \DateTime());
        $lead->setDateModified(new \DateTime());
        $this->em->persist($lead);
        $this->em->flush();

        $companyModel  = self::getContainer()->get('mautic.lead.model.company');
        assert($companyModel instanceof \Mautic\LeadBundle\Model\CompanyModel);
        $companyModel->addLeadToCompany($company, $lead);
    }

    private function addCompanyToCompanySegment(Company $company, CompanySegment $companySegment): void
    {
        $companiesSegments = new CompaniesSegments();
        $companiesSegments->setCompany($company);
        $companiesSegments->setCompanySegment($companySegment);
        $companiesSegments->setDateAdded(new \DateTime());
        $this->em->persist($companiesSegments);
        $this->em->flush();
    }
}
