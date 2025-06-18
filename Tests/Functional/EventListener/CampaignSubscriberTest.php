<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Functional\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\Lead as CampaignMember;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EnablePluginTrait;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;

class CampaignSubscriberTest extends MauticMysqlTestCase
{
    use EnablePluginTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin(true);
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    /**
     * This test we create
     * 3 leads
     * 2 companies
     * 4 company segments
     *  - a with company A
     *  - a with company B
     *  - a with Both companies
     *  - a with empty.
     *
     * In company segment event:
     *  - add companies in company segment empty
     *  - remove companies from company segment with Both
     *  - remove companies from company segment with company A
     */
    public function testCompanySegmentActionAddAndRemoveA(): void
    {
        $this->activePlugin();
        $leadJoeGlibi  = $this->createLead('joe@glibi.com', 'Joe');
        $leadMaryGlibi = $this->createLead('mary@glibi.com', 'Mary');
        $leadJohnTBS   = $this->createLead('mary@tbs.com', 'John');

        $companyGlibi = $this->createCompany('Glibi');
        $companyTBS   = $this->createCompany('TBS');

        $this->addLeadToCompany($leadJoeGlibi, $companyGlibi);
        $this->addLeadToCompany($leadMaryGlibi, $companyGlibi);
        $this->addLeadToCompany($leadJohnTBS, $companyTBS);

        $companySegmentGlibi = $this->createCompanySegment('Company Glibi', 'company-glibi', true);
        $companySegmentTBS   = $this->createCompanySegment('Company TBS', 'company-tbs', true);
        $companySegmentAll   = $this->createCompanySegment('Company All', 'company-all', true);
        $companySegmentEmpty = $this->createCompanySegment('Company Empty', 'company-empty', true);

        $this->addCompanyToCompanySegment($companyGlibi, $companySegmentGlibi);
        $this->addCompanyToCompanySegment($companyTBS, $companySegmentTBS);
        $this->addCompanyToCompanySegment($companyGlibi, $companySegmentAll);
        $this->addCompanyToCompanySegment($companyTBS, $companySegmentAll);

        /**
         * ADD event to Campaign
         * ADD Modify Company Tags in Campaign.
         */
        $modifyCompSegmentsAction = $this->createEventModifyCompanySegment(
            'Add Company Segment / Remove Segment2',
            'company_segments.action.modify',
            [
                'addToLists'      => [$companySegmentEmpty->getId()],
                'removeFromLists' => [$companySegmentAll->getId(), $companySegmentGlibi->getId()],
            ]
        );

        $totalCompaniesCompanySegmentGlibiBefore = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentGlibi]);
        $totalCompaniesCompanySegmentEmptyBefore = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentEmpty]);
        $totalCompaniesCompanySegmentAllBefore   = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentAll]);

        self::assertEmpty($totalCompaniesCompanySegmentEmptyBefore);
        self::assertCount(2, $totalCompaniesCompanySegmentAllBefore);
        self::assertCount(1, $totalCompaniesCompanySegmentGlibiBefore);

        $campaign = new Campaign();
        $campaign->setName('Campaign A');
        $campaign->addEvents([$modifyCompSegmentsAction]);

        $modifyCompSegmentsAction->setCampaign($campaign);

        $this->em->persist($campaign);
        $this->em->flush();

        $campaignLeadJoe  = $this->addLeadInCampaign($campaign, $leadJoeGlibi);
        $campaignLeadMary = $this->addLeadInCampaign($campaign, $leadMaryGlibi);
        $campaignLeadJohn = $this->addLeadInCampaign($campaign, $leadJohnTBS);

        $campaign->addLead(0, $campaignLeadJoe);
        $campaign->addLead(1, $campaignLeadMary);
        $campaign->addLead(2, $campaignLeadJohn);

        $this->em->persist($modifyCompSegmentsAction);
        $this->em->persist($campaignLeadJoe);
        $this->em->persist($campaignLeadMary);
        $this->em->persist($campaignLeadJohn);
        $this->em->persist($campaign);
        $this->em->flush();

        $campaign->setCanvasSettings(
            [
                'nodes' => [
                    [
                        'id'        => $modifyCompSegmentsAction->getId(),
                        'positionX' => '1080',
                        'positionY' => '155',
                    ],
                    [
                        'id'        => 'lists',
                        'positionX' => '1180',
                        'positionY' => '50',
                    ],
                ],
                'connections' => [
                    [
                        'sourceId' => 'lists',
                        'targetId' => $modifyCompSegmentsAction->getId(),
                        'anchors'  => [
                            [
                                'endpoint' => 'leadsource',
                                'eventId'  => 'lists',
                            ],
                            [
                                'endpoint' => 'top',
                                'eventId'  => $modifyCompSegmentsAction->getId(),
                            ],
                        ],
                    ],
                ],
            ]
        );
        $this->em->persist($campaign);
        $this->em->flush();

        $this->testSymfonyCommand('mautic:campaigns:trigger', ['-i' => $campaign->getId()]);

        $totalCompaniesCompanySegmentGlibiAfter = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentGlibi]);
        $totalCompaniesCompanySegmentEmptyAfter = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentEmpty]);
        $totalCompaniesCompanySegmentAllAfter   = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentAll]);

        self::assertCount(2, $totalCompaniesCompanySegmentEmptyAfter);
        self::assertCount(0, $totalCompaniesCompanySegmentAllAfter);
        self::assertCount(0, $totalCompaniesCompanySegmentGlibiAfter);
    }

    public function testCompanySegmentActionAddAndRemoveB(): void
    {
        $this->activePlugin();
        $leadJoeGlibi  = $this->createLead('joe@glibi.com', 'Joe');
        $leadMaryGlibi = $this->createLead('mary@glibi.com', 'Mary');
        $leadJohnTBS   = $this->createLead('mary@tbs.com', 'John');

        $companyGlibi = $this->createCompany('Glibi');
        $companyTBS   = $this->createCompany('TBS');

        $this->addLeadToCompany($leadJoeGlibi, $companyGlibi);
        $this->addLeadToCompany($leadMaryGlibi, $companyGlibi);
        $this->addLeadToCompany($leadJohnTBS, $companyTBS);

        $companySegmentGlibi = $this->createCompanySegment('Company Glibi', 'company-glibi', true);
        $companySegmentTBS   = $this->createCompanySegment('Company TBS', 'company-tbs', true);
        $companySegmentAll   = $this->createCompanySegment('Company All', 'company-all', true);
        $companySegmentEmpty = $this->createCompanySegment('Company Empty', 'company-empty', true);

        $this->addCompanyToCompanySegment($companyGlibi, $companySegmentGlibi);
        $this->addCompanyToCompanySegment($companyTBS, $companySegmentTBS);
        $this->addCompanyToCompanySegment($companyGlibi, $companySegmentAll);
        $this->addCompanyToCompanySegment($companyTBS, $companySegmentAll);

        /**
         * ADD event to Campaign
         * ADD Modify Company Tags in Campaign.
         */
        $modifyCompSegmentsAction = $this->createEventModifyCompanySegment(
            'Add Company Segment / Remove Segment2',
            'company_segments.action.modify',
            [
                'addToLists'      => [$companySegmentEmpty->getId()],
                'removeFromLists' => [$companySegmentAll->getId()],
            ]
        );

        $totalCompaniesCompanySegmentGlibiBefore = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentGlibi]);
        $totalCompaniesCompanySegmentEmptyBefore = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentEmpty]);
        $totalCompaniesCompanySegmentAllBefore   = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentAll]);

        self::assertEmpty($totalCompaniesCompanySegmentEmptyBefore);
        self::assertCount(2, $totalCompaniesCompanySegmentAllBefore);
        self::assertCount(1, $totalCompaniesCompanySegmentGlibiBefore);

        $campaign = new Campaign();
        $campaign->setName('Campaign A');
        $campaign->addEvents([$modifyCompSegmentsAction]);

        $modifyCompSegmentsAction->setCampaign($campaign);

        $this->em->persist($campaign);
        $this->em->flush();

        $campaignLeadJoe  = $this->addLeadInCampaign($campaign, $leadJoeGlibi);
        $campaignLeadMary = $this->addLeadInCampaign($campaign, $leadMaryGlibi);
        //        $campaignLeadJohn = $this->addLeadInCampaign($campaign, $leadJohnTBS);

        $campaign->addLead(0, $campaignLeadJoe);
        $campaign->addLead(1, $campaignLeadMary);
        //        $campaign->addLead(2, $campaignLeadJohn);

        $this->em->persist($modifyCompSegmentsAction);
        $this->em->persist($campaignLeadJoe);
        $this->em->persist($campaignLeadMary);
        //        $this->em->persist($campaignLeadJohn);
        $this->em->persist($campaign);
        $this->em->flush();

        $campaign->setCanvasSettings(
            [
                'nodes' => [
                    [
                        'id'        => $modifyCompSegmentsAction->getId(),
                        'positionX' => '1080',
                        'positionY' => '155',
                    ],
                    [
                        'id'        => 'lists',
                        'positionX' => '1180',
                        'positionY' => '50',
                    ],
                ],
                'connections' => [
                    [
                        'sourceId' => 'lists',
                        'targetId' => $modifyCompSegmentsAction->getId(),
                        'anchors'  => [
                            [
                                'endpoint' => 'leadsource',
                                'eventId'  => 'lists',
                            ],
                            [
                                'endpoint' => 'top',
                                'eventId'  => $modifyCompSegmentsAction->getId(),
                            ],
                        ],
                    ],
                ],
            ]
        );
        $this->em->persist($campaign);
        $this->em->flush();

        $this->testSymfonyCommand('mautic:campaigns:trigger', ['-i' => $campaign->getId()]);

        $totalCompaniesCompanySegmentGlibiAfter = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentGlibi]);
        $totalCompaniesCompanySegmentEmptyAfter = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentEmpty]);
        $totalCompaniesCompanySegmentAllAfter   = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentAll]);

        self::assertCount(1, $totalCompaniesCompanySegmentGlibiAfter);
        self::assertCount(1, $totalCompaniesCompanySegmentEmptyAfter);
        self::assertCount(1, $totalCompaniesCompanySegmentAllAfter);
    }

    public function testCompanySegmentAddActionWithLeadWithoutPrimaryCompany(): void
    {
        $this->activePlugin();
        $leadJoeGlibi  = $this->createLead('joe@glibi.com', 'Joe');
        $leadMaryGlibi = $this->createLead('mary@glibi.com', 'Mary');
        $leadJohnTBS   = $this->createLead('mary@tbs.com', 'John');

        $companyGlibi = $this->createCompany('Glibi');
        $companyTBS   = $this->createCompany('TBS');

        $companySegmentGlibi = $this->createCompanySegment('Company Glibi', 'company-glibi', true);
        $companySegmentTBS   = $this->createCompanySegment('Company TBS', 'company-tbs', true);
        $companySegmentAll   = $this->createCompanySegment('Company All', 'company-all', true);
        $companySegmentEmpty = $this->createCompanySegment('Company Empty', 'company-empty', true);

        $this->addCompanyToCompanySegment($companyGlibi, $companySegmentGlibi);
        $this->addCompanyToCompanySegment($companyTBS, $companySegmentTBS);
        $this->addCompanyToCompanySegment($companyGlibi, $companySegmentAll);
        $this->addCompanyToCompanySegment($companyTBS, $companySegmentAll);

        /**
         * ADD event to Campaign
         * ADD Modify Company Tags in Campaign.
         */
        $modifyCompSegmentsAction = $this->createEventModifyCompanySegment(
            'Add Company Segment / Remove Segment2',
            'company_segments.action.modify',
            [
                'addToLists'      => [$companySegmentEmpty->getId()],
                'removeFromLists' => [$companySegmentAll->getId()],
            ]
        );

        $totalCompaniesCompanySegmentGlibiBefore = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentGlibi]);
        $totalCompaniesCompanySegmentEmptyBefore = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentEmpty]);
        $totalCompaniesCompanySegmentAllBefore   = $this->em->getRepository(CompaniesSegments::class)->findBy(['companySegment'=>$companySegmentAll]);

        self::assertEmpty($totalCompaniesCompanySegmentEmptyBefore);
        self::assertCount(2, $totalCompaniesCompanySegmentAllBefore);
        self::assertCount(1, $totalCompaniesCompanySegmentGlibiBefore);

        $campaign = new Campaign();
        $campaign->setName('Campaign A');
        $campaign->addEvents([$modifyCompSegmentsAction]);

        $modifyCompSegmentsAction->setCampaign($campaign);

        $this->em->persist($campaign);
        $this->em->flush();

        $campaignLeadJoe  = $this->addLeadInCampaign($campaign, $leadJoeGlibi);
        $campaignLeadMary = $this->addLeadInCampaign($campaign, $leadMaryGlibi);

        $campaign->addLead(0, $campaignLeadJoe);
        $campaign->addLead(1, $campaignLeadMary);
        //        $campaign->addLead(2, $campaignLeadJohn);

        $this->em->persist($modifyCompSegmentsAction);
        $this->em->persist($campaignLeadJoe);
        $this->em->persist($campaignLeadMary);
        //        $this->em->persist($campaignLeadJohn);
        $this->em->persist($campaign);
        $this->em->flush();

        $campaign->setCanvasSettings(
            [
                'nodes' => [
                    [
                        'id'        => $modifyCompSegmentsAction->getId(),
                        'positionX' => '1080',
                        'positionY' => '155',
                    ],
                    [
                        'id'        => 'lists',
                        'positionX' => '1180',
                        'positionY' => '50',
                    ],
                ],
                'connections' => [
                    [
                        'sourceId' => 'lists',
                        'targetId' => $modifyCompSegmentsAction->getId(),
                        'anchors'  => [
                            [
                                'endpoint' => 'leadsource',
                                'eventId'  => 'lists',
                            ],
                            [
                                'endpoint' => 'top',
                                'eventId'  => $modifyCompSegmentsAction->getId(),
                            ],
                        ],
                    ],
                ],
            ]
        );
        $this->em->persist($campaign);
        $this->em->flush();

        $this->testSymfonyCommand('mautic:campaigns:trigger', ['-i' => $campaign->getId()]);

        $this->client->request('GET', '/s/contacts/timeline/'.$leadJoeGlibi->getId());
        $content = $this->client->getResponse()->getContent();
        self::assertNotFalse($content);
        self::assertStringNotContainsString('ri-alert-line text-danger', $content);
    }

    public function testCompanySegmentAddCondition(): void
    {
        $this->activePlugin();
        $leadJoeGlibi  = $this->createLead('joe@glibi.com', 'Joe');
        $leadMaryGlibi = $this->createLead('mary@glibi.com', 'Mary');
        $leadJohnTBS   = $this->createLead('mary@tbs.com', 'John');

        self::assertNull($leadJoeGlibi->getLastname());
        self::assertNull($leadMaryGlibi->getLastname());
        self::assertNull($leadJohnTBS->getLastname());

        $companyGlibi = $this->createCompany('Glibi');
        $companyTBS   = $this->createCompany('TBS');

        $this->addLeadToCompany($leadJoeGlibi, $companyGlibi);
        $this->addLeadToCompany($leadMaryGlibi, $companyGlibi);
        $this->addLeadToCompany($leadJohnTBS, $companyTBS);

        $companySegmentGlibi = $this->createCompanySegment('Company Glibi', 'company-glibi', true);
        $companySegmentTBS   = $this->createCompanySegment('Company TBS', 'company-tbs', true);
        $companySegmentAll   = $this->createCompanySegment('Company All', 'company-all', true);
        $companySegmentEmpty = $this->createCompanySegment('Company Empty', 'company-empty', true);

        $this->addCompanyToCompanySegment($companyGlibi, $companySegmentGlibi);
        $this->addCompanyToCompanySegment($companyTBS, $companySegmentTBS);
        $this->addCompanyToCompanySegment($companyGlibi, $companySegmentAll);
        $this->addCompanyToCompanySegment($companyTBS, $companySegmentAll);

        /**
         * ADD event to Campaign
         * ADD Modify Company Tags in Campaign.
         */
        $modifyCompSegmentsCondition = $this->createEventModifyCompanySegment(
            'condition if have company segment glibi',
            'company_segments.condition.modify',
            [
                'companySegments'    => [$companySegmentGlibi->getId()],
            ],
            'condition',
            1
        );

        $lastnameYES = 'HAHAHA';
        $lastnameNO  = 'HOHOHO';

        $eventDontHaveSegment = $this->createEventModifyCompanySegment(
            'Add last name hohoh',
            'lead.updatelead',
            [
                'lastname'    => $lastnameNO,
            ],
            'action',
            3,
            'no',
            $modifyCompSegmentsCondition
        );

        $eventHaveSegment = $this->createEventModifyCompanySegment(
            'Add last name hahah',
            'lead.updatelead',
            [
                'lastname'    => $lastnameYES,
            ],
            'action',
            2,
            'yes',
            $modifyCompSegmentsCondition
        );

        $campaign = new Campaign();
        $campaign->setName('Campaign A');
        $campaign->addEvents([$modifyCompSegmentsCondition, $eventDontHaveSegment, $eventHaveSegment]);

        $modifyCompSegmentsCondition->setCampaign($campaign);
        $eventHaveSegment->setCampaign($campaign);
        $eventDontHaveSegment->setCampaign($campaign);

        $campaignLeadJoe  = $this->addLeadInCampaign($campaign, $leadJoeGlibi);
        $campaignLeadMary = $this->addLeadInCampaign($campaign, $leadMaryGlibi);
        $campaignLeadJohn = $this->addLeadInCampaign($campaign, $leadJohnTBS);

        $campaign->addLead(0, $campaignLeadJoe);
        $campaign->addLead(1, $campaignLeadMary);
        $campaign->addLead(2, $campaignLeadJohn);

        $this->em->persist($modifyCompSegmentsCondition);
        $this->em->persist($eventHaveSegment);
        $this->em->persist($eventDontHaveSegment);
        $this->em->persist($campaignLeadJoe);
        $this->em->persist($campaignLeadMary);
        $this->em->persist($campaignLeadJohn);
        $this->em->persist($campaign);
        $this->em->flush();

        $campaign->setCanvasSettings(
            [
                'nodes' => [
                    [
                        'id'        => $modifyCompSegmentsCondition->getId(),
                        'positionX' => '420',
                        'positionY' => '155',
                    ],
                    [
                        'id'        => $eventHaveSegment->getId(),
                        'positionX' => '176',
                        'positionY' => '155',
                    ],
                    [
                        'id'        => $eventDontHaveSegment->getId(),
                        'positionX' => '847',
                        'positionY' => '155',
                    ],
                    [
                        'id'        => 'lists',
                        'positionX' => '896',
                        'positionY' => '50',
                    ],
                ],
                'connections' => [
                    [
                        'sourceId' => 'lists',
                        'targetId' => $modifyCompSegmentsCondition->getId(),
                        'anchors'  => [
                            [
                                'endpoint' => 'leadsource',
                                'eventId'  => 'lists',
                            ],
                            [
                                'endpoint' => 'top',
                                'eventId'  => $modifyCompSegmentsCondition->getId(),
                            ],
                        ],
                    ],
                    [
                        'sourceId' => $modifyCompSegmentsCondition->getId(),
                        'targetId' => $eventHaveSegment->getId(),
                        'anchors'  => [
                            [
                                'endpoint' => 'yes',
                                'eventId'  => $modifyCompSegmentsCondition->getId(),
                            ],
                            [
                                'endpoint' => 'top',
                                'eventId'  => $eventHaveSegment->getId(),
                            ],
                        ],
                    ],
                    [
                        'sourceId' => $modifyCompSegmentsCondition->getId(),
                        'targetId' => $eventDontHaveSegment->getId(),
                        'anchors'  => [
                            [
                                'endpoint' => 'no',
                                'eventId'  => $modifyCompSegmentsCondition->getId(),
                            ],
                            [
                                'endpoint' => 'top',
                                'eventId'  => $eventDontHaveSegment->getId(),
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this->em->persist($campaign);
        $this->em->flush();
        $this->em->clear();

        $this->testSymfonyCommand('mautic:campaigns:trigger', ['-i' => $campaign->getId()]);

        $leadJoeGlibiAfter  = $this->em->getRepository(Lead::class)->find($leadJoeGlibi->getId());
        $leadMaryGlibiAfter = $this->em->getRepository(Lead::class)->find($leadMaryGlibi->getId());
        $leadJohnTBSAfter   = $this->em->getRepository(Lead::class)->find($leadJohnTBS->getId());

        self::assertNotNull($leadJoeGlibiAfter);
        self::assertSame($lastnameYES, $leadJoeGlibiAfter->getLastname());
        self::assertNotNull($leadMaryGlibiAfter);
        self::assertSame($lastnameYES, $leadMaryGlibiAfter->getLastname());
        self::assertNotNull($leadJohnTBSAfter);
        self::assertSame($lastnameNO, $leadJohnTBSAfter->getLastname());
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

    private function activePlugin(bool $isPublished = true): void
    {
        $this->client->request('GET', '/s/plugins/reload');
        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => 'LeuchtfeuerCompanySegments']);
        if (null === $integration) {
            $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => 'LeuchtfeuerCompanySegmentsBundle']);
            $integration = new Integration();
            $integration->setName('LeuchtfeuerCompanySegments');
            $integration->setPlugin($plugin);
            $integration->setApiKeys([]);
        }
        $integration->setIsPublished($isPublished);
        $integrationRepository = $this->em->getRepository(Integration::class);
        assert($integrationRepository instanceof \Mautic\PluginBundle\Entity\IntegrationRepository);
        $integrationRepository->saveEntity($integration);
        $this->em->persist($integration);
        $this->em->flush();
    }
}
