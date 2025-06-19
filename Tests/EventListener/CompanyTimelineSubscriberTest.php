<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EventListener;

use Mautic\CoreBundle\Translation\Translator;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLogRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanyTimelineEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener\CompanyTimelineSubscriber;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class CompanyTimelineSubscriberTest extends TestCase
{
    private $companyEventLogModel;
    private $translator;
    private $companySegmentModel;
    private $router;
    private $companyEventLogRepository;

    protected function setUp(): void
    {
        $this->companyEventLogModel      = $this->createMock(CompanyEventLogModel::class);
        $this->translator                = $this->createMock(Translator::class);
        $this->companySegmentModel       = $this->createMock(CompanySegmentModel::class);
        $this->router                    = $this->createMock(RouterInterface::class);
        $this->companyEventLogRepository = $this->createMock(CompanyEventLogRepository::class);
        $data                            = [
            'total'   => 2,
            'results' => [
                [
                    'id'          => 1,
                    'eventType'   => 'company.segmentadd',
                    'description' => 'Segment added',
                    'dateAdded'   => '2023-10-01 12:00:00',
                    'company_id'  => 123,
                    'object_id'   => 12,
                    'date_added'  => new \DateTime('2023-10-01 12:00:00'),
                    'properties'  => json_encode([
                        'object_description' => 'Segment Name 1',
                    ]),
                ],
                [
                    'id'          => 2,
                    'eventType'   => 'company.segmentremove',
                    'description' => 'Segment removed',
                    'dateAdded'   => '2023-10-02 14:00:00',
                    'company_id'  => 123,
                    'object_id'   => 13,
                    'date_added'  => new \DateTime('2023-10-02 12:00:00'),
                    'properties'  => json_encode([
                        'object_description' => 'Segment Name 2',
                    ]),
                ],
            ],
        ];
        $this->companyEventLogRepository
            ->method('getEvents')
            ->willReturn($data);
    }

    public function testGetSubscribedEvents()
    {
        $events = CompanyTimelineSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey('leuchtfeuer_company_segment.timeline_event', $events);
    }

    public function testOnTimelineGenerateAddsEventTypes()
    {
        $subscriber = new CompanyTimelineSubscriber(
            $this->companyEventLogModel,
            $this->translator,
            $this->companySegmentModel,
            $this->router,
            $this->companyEventLogRepository,
        );

        $event = $this->createMock(CompanyTimelineEvent::class);
        $event->expects($this->exactly(4))->method('addEventType');
        $event->method('isApplicable')->willReturn(true);
        $event->method('getCompany')->willReturn($this->createMock(\Mautic\LeadBundle\Entity\Company::class));
        $this->translator->method('trans')->willReturnArgument(0);

        $subscriber->onTimelineGenerate($event);
    }
}
