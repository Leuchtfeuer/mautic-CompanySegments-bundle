<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EventListener;

use Mautic\DynamicContentBundle\Event\ContactFiltersEvaluateEvent;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Exception\PrimaryCompanyNotFoundException;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener\DynamicContentSubscriber;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DynamicContentSubscriberTest extends TestCase
{
    private CompanySegmentRepository&MockObject $companySegmentRepository;
    private CompanyLeadRepository&MockObject $companyLeadRepository;
    private Config&MockObject $config;
    private DynamicContentSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->companySegmentRepository = $this->createMock(CompanySegmentRepository::class);
        $this->companyLeadRepository    = $this->getMockBuilder(CompanyLeadRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPrimaryCompanyByLeadId'])
            ->getMock();
        $this->config                   = $this->createMock(Config::class);

        $this->subscriber = new DynamicContentSubscriber(
            $this->companySegmentRepository,
            $this->companyLeadRepository,
            $this->config
        );
    }

    public function testSubscriberDoesNothingWhenPluginNotPublished(): void
    {
        $this->config->method('isPublished')->willReturn(false);

        $lead    = $this->createMock(Lead::class);
        $filters = [
            [
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'operator' => 'in',
                'filter'   => [1, 2],
            ],
        ];

        $event = new ContactFiltersEvaluateEvent($filters, $lead);
        $this->subscriber->onContactFilterEvaluate($event);

        $this->assertFalse($event->isEvaluated());
    }

    public function testSubscriberIgnoresNonCompanySegmentFilters(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead    = $this->createMock(Lead::class);
        $filters = [
            [
                'field'    => 'email',
                'object'   => 'lead',
                'type'     => 'text',
                'operator' => 'like',
                'filter'   => 'test@example.com',
            ],
        ];

        $event = new ContactFiltersEvaluateEvent($filters, $lead);
        $this->subscriber->onContactFilterEvaluate($event);

        $this->assertFalse($event->isEvaluated());
    }

    public function testOperatorInMatchesWhenCompanyInSegments(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead = $this->createMock(Lead::class);
        $lead->method('getId')->willReturn(123);

        $this->companyLeadRepository->method('getPrimaryCompanyByLeadId')
            ->with(123)
            ->willReturn(['id' => 456]);

        $this->companySegmentRepository->method('isCompanyInSegments')
            ->with(456, [1, 2])
            ->willReturn(true);

        $filters = [
            [
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'operator' => 'in',
                'filter'   => [1, 2],
            ],
        ];

        $event = new ContactFiltersEvaluateEvent($filters, $lead);
        $this->subscriber->onContactFilterEvaluate($event);

        $this->assertTrue($event->isEvaluated());
        $this->assertTrue($event->isMatched());
    }

    public function testOperatorInDoesNotMatchWhenCompanyNotInSegments(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead = $this->createMock(Lead::class);
        $lead->method('getId')->willReturn(123);

        $this->companyLeadRepository->method('getPrimaryCompanyByLeadId')
            ->with(123)
            ->willReturn(['id' => 456]);

        $this->companySegmentRepository->method('isCompanyInSegments')
            ->with(456, [1, 2])
            ->willReturn(false);

        $filters = [
            [
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'operator' => 'in',
                'filter'   => [1, 2],
            ],
        ];

        $event = new ContactFiltersEvaluateEvent($filters, $lead);
        $this->subscriber->onContactFilterEvaluate($event);

        $this->assertTrue($event->isEvaluated());
        $this->assertFalse($event->isMatched());
    }

    public function testOperatorNotInMatchesWhenCompanyNotInSegments(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead = $this->createMock(Lead::class);
        $lead->method('getId')->willReturn(123);

        $this->companyLeadRepository->method('getPrimaryCompanyByLeadId')
            ->with(123)
            ->willReturn(['id' => 456]);

        $this->companySegmentRepository->method('isNotCompanyInSegments')
            ->with(456, [1, 2])
            ->willReturn(true);

        $filters = [
            [
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'operator' => '!in',
                'filter'   => [1, 2],
            ],
        ];

        $event = new ContactFiltersEvaluateEvent($filters, $lead);
        $this->subscriber->onContactFilterEvaluate($event);

        $this->assertTrue($event->isEvaluated());
        $this->assertTrue($event->isMatched());
    }

    public function testOperatorEmptyMatchesWhenLeadHasNoPrimaryCompany(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead = $this->createMock(Lead::class);
        $lead->method('getId')->willReturn(123);

        $this->companyLeadRepository->method('getPrimaryCompanyByLeadId')
            ->with(123)
            ->willThrowException(new PrimaryCompanyNotFoundException());

        $filters = [
            [
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'operator' => 'empty',
                'filter'   => null,
            ],
        ];

        $event = new ContactFiltersEvaluateEvent($filters, $lead);
        $this->subscriber->onContactFilterEvaluate($event);

        $this->assertTrue($event->isEvaluated());
        $this->assertTrue($event->isMatched());
    }

    public function testOperatorEmptyMatchesWhenCompanyNotInAnySegment(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead = $this->createMock(Lead::class);
        $lead->method('getId')->willReturn(123);

        $this->companyLeadRepository->method('getPrimaryCompanyByLeadId')
            ->with(123)
            ->willReturn(['id' => 456]);

        $this->companySegmentRepository->method('isCompanyInAnySegment')
            ->with(456)
            ->willReturn(false);

        $filters = [
            [
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'operator' => 'empty',
                'filter'   => null,
            ],
        ];

        $event = new ContactFiltersEvaluateEvent($filters, $lead);
        $this->subscriber->onContactFilterEvaluate($event);

        $this->assertTrue($event->isEvaluated());
        $this->assertTrue($event->isMatched());
    }

    public function testOperatorNotEmptyMatchesWhenCompanyInAnySegment(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead = $this->createMock(Lead::class);
        $lead->method('getId')->willReturn(123);

        $this->companyLeadRepository->method('getPrimaryCompanyByLeadId')
            ->with(123)
            ->willReturn(['id' => 456]);

        $this->companySegmentRepository->method('isCompanyInAnySegment')
            ->with(456)
            ->willReturn(true);

        $filters = [
            [
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'operator' => '!empty',
                'filter'   => null,
            ],
        ];

        $event = new ContactFiltersEvaluateEvent($filters, $lead);
        $this->subscriber->onContactFilterEvaluate($event);

        $this->assertTrue($event->isEvaluated());
        $this->assertTrue($event->isMatched());
    }

    public function testOperatorNotEmptyDoesNotMatchWhenLeadHasNoPrimaryCompany(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead = $this->createMock(Lead::class);
        $lead->method('getId')->willReturn(123);

        $this->companyLeadRepository->method('getPrimaryCompanyByLeadId')
            ->with(123)
            ->willThrowException(new PrimaryCompanyNotFoundException());

        $filters = [
            [
                'field'    => 'company_segments',
                'object'   => 'company_segments',
                'type'     => 'company_segments',
                'operator' => '!empty',
                'filter'   => null,
            ],
        ];

        $event = new ContactFiltersEvaluateEvent($filters, $lead);
        $this->subscriber->onContactFilterEvaluate($event);

        $this->assertTrue($event->isEvaluated());
        $this->assertFalse($event->isMatched());
    }
}
