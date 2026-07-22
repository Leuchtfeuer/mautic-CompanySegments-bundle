<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EventListener;

use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Exception\PrimaryCompanyNotFoundException;
use Mautic\LeadBundle\Helper\PrimaryCompanyHelper;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener\EmailDynamicContentSubscriber;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EmailDynamicContentSubscriberTest extends TestCase
{
    private CompanySegmentRepository&MockObject $companySegmentRepository;
    private CompanyLeadRepository&MockObject $companyLeadRepository;
    private Config&MockObject $config;
    private PrimaryCompanyHelper&MockObject $primaryCompanyHelper;
    private EventDispatcherInterface&MockObject $dispatcher;
    private LeadListRepository&MockObject $segmentRepository;
    private EmailDynamicContentSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->companySegmentRepository = $this->createMock(CompanySegmentRepository::class);
        $this->companyLeadRepository    = $this->getMockBuilder(CompanyLeadRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPrimaryCompanyByLeadId'])
            ->getMock();
        $this->config               = $this->createMock(Config::class);
        $this->primaryCompanyHelper = $this->createMock(PrimaryCompanyHelper::class);
        $this->dispatcher           = $this->createMock(EventDispatcherInterface::class);
        $this->segmentRepository    = $this->getMockBuilder(LeadListRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->subscriber = new EmailDynamicContentSubscriber(
            $this->companySegmentRepository,
            $this->companyLeadRepository,
            $this->config,
            $this->primaryCompanyHelper,
            $this->dispatcher,
            $this->segmentRepository,
        );
    }

    public function testDoesNothingWhenPluginNotPublished(): void
    {
        $this->config->method('isPublished')->willReturn(false);
        $this->dispatcher->expects(self::never())->method('dispatch');

        $event = $this->makeEvent(['id' => 1, 'email' => 'a@b.com'], $this->makeDcClickthrough('token1', 'in', [1]));
        $this->subscriber->onTokenReplacement($event);
    }

    public function testDoesNothingWhenNoDynamicContent(): void
    {
        $this->config->method('isPublished')->willReturn(true);
        $this->dispatcher->expects(self::never())->method('dispatch');

        $event = new TokenReplacementEvent(null, ['id' => 1], ['tokens' => []], null);
        $this->subscriber->onTokenReplacement($event);
    }

    public function testSkipsItemsWithoutCompanySegmentsFilter(): void
    {
        $this->config->method('isPublished')->willReturn(true);
        $this->dispatcher->expects(self::never())->method('dispatch');

        $clickthrough = [
            'tokens'         => [],
            'dynamicContent' => [
                [
                    'tokenName' => 'token1',
                    'content'   => 'default',
                    'filters'   => [
                        ['content' => 'match', 'filters' => [['type' => 'leadlist', 'operator' => 'in', 'filter' => [1]]]],
                    ],
                ],
            ],
        ];

        $event = new TokenReplacementEvent(null, ['id' => 1], $clickthrough, null);
        $this->subscriber->onTokenReplacement($event);

        // Item must remain in clickthrough so TokenSubscriber handles it
        $remaining = $event->getClickthrough()['dynamicContent'];
        \assert(is_array($remaining));
        self::assertCount(1, $remaining);
    }

    public function testHandlesCompanySegmentsItemAndRemovesFromClickthrough(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead = ['id' => 10, 'email' => 'a@b.com'];
        $this->primaryCompanyHelper->method('mergePrimaryCompanyWithProfileFields')->willReturn($lead);

        $this->companyLeadRepository->method('getPrimaryCompanyByLeadId')->with(10)->willReturn(['id' => 99]);
        $this->companySegmentRepository->method('isCompanyInSegments')->with(99, [5])->willReturn(true);

        $this->dispatcher->expects(self::once())->method('dispatch')
            ->with(self::isInstanceOf(EmailSendEvent::class), EmailEvents::EMAIL_ON_DISPLAY)
            ->willReturnArgument(0);

        $event = $this->makeEvent($lead, $this->makeDcClickthrough('myToken', 'in', [5], 'matched content', 'default'));
        $this->subscriber->onTokenReplacement($event);

        self::assertSame('matched content', $event->getTokens()['{dynamiccontent="myToken"}']);
        self::assertEmpty($event->getClickthrough()['dynamicContent']);
    }

    public function testUsesDefaultContentWhenFilterDoesNotMatch(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead = ['id' => 10, 'email' => 'a@b.com'];
        $this->primaryCompanyHelper->method('mergePrimaryCompanyWithProfileFields')->willReturn($lead);

        $this->companyLeadRepository->method('getPrimaryCompanyByLeadId')->with(10)->willReturn(['id' => 99]);
        $this->companySegmentRepository->method('isCompanyInSegments')->with(99, [5])->willReturn(false);

        $this->dispatcher->method('dispatch')->willReturnArgument(0);

        $event = $this->makeEvent($lead, $this->makeDcClickthrough('myToken', 'in', [5], 'matched content', 'default'));
        $this->subscriber->onTokenReplacement($event);

        self::assertSame('default', $event->getTokens()['{dynamiccontent="myToken"}']);
    }

    public function testUsesLeadEntityViaGetProfileFields(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead      = $this->createMock(Lead::class);
        $leadArray = ['id' => 7, 'email' => 'x@y.com'];
        $this->primaryCompanyHelper->method('getProfileFieldsWithPrimaryCompany')->with($lead)->willReturn($leadArray);

        $this->companyLeadRepository->method('getPrimaryCompanyByLeadId')->with(7)->willReturn(['id' => 20]);
        $this->companySegmentRepository->method('isCompanyInSegments')->willReturn(true);
        $this->dispatcher->method('dispatch')->willReturnArgument(0);

        $clickthrough = array_merge($this->makeDcClickthrough('t', 'in', [1]), ['tokens' => []]);
        $event        = new TokenReplacementEvent(null, $lead, $clickthrough, null);
        $this->subscriber->onTokenReplacement($event);

        self::assertArrayHasKey('{dynamiccontent="t"}', $event->getTokens());
    }

    public function testNoPrimaryCompanyWithEmptyOperatorMatches(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead = ['id' => 10, 'email' => 'a@b.com'];
        $this->primaryCompanyHelper->method('mergePrimaryCompanyWithProfileFields')->willReturn($lead);
        $this->companyLeadRepository->method('getPrimaryCompanyByLeadId')
            ->willThrowException(new PrimaryCompanyNotFoundException());

        $this->dispatcher->method('dispatch')->willReturnArgument(0);

        $event = $this->makeEvent($lead, $this->makeDcClickthrough('t', 'empty', [], 'matched', 'default'));
        $this->subscriber->onTokenReplacement($event);

        self::assertSame('matched', $event->getTokens()['{dynamiccontent="t"}']);
    }

    /** Contact segment AND company segment both match → show variant content. */
    public function testMixedAndConditionsAllMatchShowsVariant(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead = ['id' => 10, 'email' => 'a@b.com'];
        $this->primaryCompanyHelper->method('mergePrimaryCompanyWithProfileFields')->willReturn($lead);
        $this->companyLeadRepository->method('getPrimaryCompanyByLeadId')->willReturn(['id' => 99]);
        $this->companySegmentRepository->method('isCompanyInSegments')->willReturn(true);
        $this->segmentRepository->method('isContactInSegments')->willReturn(true);
        $this->dispatcher->method('dispatch')->willReturnArgument(0);

        $clickthrough = [
            'tokens'         => [],
            'dynamicContent' => [[
                'tokenName' => 't',
                'content'   => 'default',
                'filters'   => [[
                    'content' => 'matched',
                    'filters' => [
                        ['type' => 'leadlist', 'field' => 'leadlist', 'operator' => 'in', 'filter' => [1], 'glue' => 'and'],
                        ['type' => 'company_segments', 'operator' => 'in', 'filter' => [5], 'glue' => 'and'],
                    ],
                ]],
            ]],
        ];

        $event = $this->makeEvent($lead, $clickthrough);
        $this->subscriber->onTokenReplacement($event);

        self::assertSame('matched', $event->getTokens()['{dynamiccontent="t"}']);
    }

    /** Company segment matches but AND contact segment fails → show default. */
    public function testMixedAndConditionsOneFailsShowsDefault(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead = ['id' => 10, 'email' => 'a@b.com'];
        $this->primaryCompanyHelper->method('mergePrimaryCompanyWithProfileFields')->willReturn($lead);
        $this->companyLeadRepository->method('getPrimaryCompanyByLeadId')->willReturn(['id' => 99]);
        $this->companySegmentRepository->method('isCompanyInSegments')->willReturn(true);
        $this->segmentRepository->method('isContactInSegments')->willReturn(false);
        $this->dispatcher->method('dispatch')->willReturnArgument(0);

        $clickthrough = [
            'tokens'         => [],
            'dynamicContent' => [[
                'tokenName' => 't',
                'content'   => 'default',
                'filters'   => [[
                    'content' => 'matched',
                    'filters' => [
                        ['type' => 'company_segments', 'operator' => 'in', 'filter' => [5], 'glue' => 'and'],
                        ['type' => 'leadlist', 'field' => 'leadlist', 'operator' => 'in', 'filter' => [1], 'glue' => 'and'],
                    ],
                ]],
            ]],
        ];

        $event = $this->makeEvent($lead, $clickthrough);
        $this->subscriber->onTokenReplacement($event);

        self::assertSame('default', $event->getTokens()['{dynamiccontent="t"}']);
    }

    /** OR-glue: first condition fails, second OR-condition passes → show variant. */
    public function testOrConditionSecondGroupMatchesShowsVariant(): void
    {
        $this->config->method('isPublished')->willReturn(true);

        $lead = ['id' => 10, 'email' => 'a@b.com'];
        $this->primaryCompanyHelper->method('mergePrimaryCompanyWithProfileFields')->willReturn($lead);
        $this->companyLeadRepository->method('getPrimaryCompanyByLeadId')->willReturn(['id' => 99]);
        $this->companySegmentRepository->method('isCompanyInSegments')
            ->willReturnOnConsecutiveCalls(false, true);
        $this->dispatcher->method('dispatch')->willReturnArgument(0);

        $clickthrough = [
            'tokens'         => [],
            'dynamicContent' => [[
                'tokenName' => 't',
                'content'   => 'default',
                'filters'   => [[
                    'content' => 'matched',
                    'filters' => [
                        ['type' => 'company_segments', 'operator' => 'in', 'filter' => [1], 'glue' => 'and'],
                        ['type' => 'company_segments', 'operator' => 'in', 'filter' => [2], 'glue' => 'or'],
                    ],
                ]],
            ]],
        ];

        $event = $this->makeEvent($lead, $clickthrough);
        $this->subscriber->onTokenReplacement($event);

        self::assertSame('matched', $event->getTokens()['{dynamiccontent="t"}']);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $clickthrough
     */
    private function makeEvent(array $lead, array $clickthrough): TokenReplacementEvent
    {
        return new TokenReplacementEvent(null, $lead, $clickthrough, null);
    }

    /**
     * @param int[] $segmentIds
     *
     * @return array<string, mixed>
     */
    private function makeDcClickthrough(
        string $tokenName,
        string $operator,
        array $segmentIds,
        string $matchContent = 'matched content',
        string $defaultContent = 'default',
    ): array {
        return [
            'tokens'         => [],
            'dynamicContent' => [
                [
                    'tokenName' => $tokenName,
                    'content'   => $defaultContent,
                    'filters'   => [
                        [
                            'content' => $matchContent,
                            'filters' => [
                                [
                                    'type'     => 'company_segments',
                                    'field'    => 'company_segments',
                                    'object'   => 'company_segments',
                                    'operator' => $operator,
                                    'filter'   => $segmentIds,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
