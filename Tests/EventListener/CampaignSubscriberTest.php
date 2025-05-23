<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EventListener;

use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener\CampaignSubscriber;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;

class CampaignSubscriberTest extends MauticMysqlTestCase
{
    public function testNotPublishedIsNotExecuted(): void
    {
        $config              = $this->createMock(Config::class);
        $config->method('isPublished')
            ->willReturn(false);
        $companySegmentModel = $this->createMock(CompanySegmentModel::class);
        $companyModel        = $this->createMock(CompanyModel::class);
        // @phpstan-ignore-next-line
        $event               = $this->createMock(CampaignExecutionEvent::class);
        // @phpstan-ignore-next-line
        $eventReturn         = $this->createMock(CampaignExecutionEvent::class);
        $eventReturn->expects(self::once())
            ->method('getResult')
            ->willReturn(false);
        $event->expects(self::once())
            ->method('setResult')
            ->with(false)
            ->willReturn($eventReturn);

        $eventBuilder        = $this->createMock(CampaignBuilderEvent::class);

        $subscriber            = new CampaignSubscriber($config, $companySegmentModel, $companyModel);
        $resultOnActionTrigger = $subscriber->onCampaignActionTriggerAction($event);
        self::assertFalse($resultOnActionTrigger->getResult());
        $oldEventBuilder = $eventBuilder;
        $subscriber->onCampaignBuild($eventBuilder);
        self::assertSame($oldEventBuilder, $eventBuilder);
    }

    public function testOnCampaignActionTriggerActionWhenPublished(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isPublished')->willReturn(true);

        $companySegmentModel = $this->createMock(CompanySegmentModel::class);
        $companyModel        = $this->createMock(CompanyModel::class);
        // @phpstan-ignore-next-line
        $eventReturn         = $this->createMock(CampaignExecutionEvent::class);
        $eventReturn->expects(self::once())
            ->method('getResult')
            ->willReturn(true);
        // @phpstan-ignore-next-line
        $event = $this->createMock(CampaignExecutionEvent::class);
        $event->expects(self::once())
            ->method('setResult')
            ->with(false)
            ->willReturn($eventReturn);

        $subscriber = new CampaignSubscriber($config, $companySegmentModel, $companyModel);
        $result     = $subscriber->onCampaignActionTriggerAction($event);

        self::assertTrue($result->getResult());
    }

    public function testOnCampaignBuildAddsAction(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isPublished')->willReturn(true);

        $companySegmentModel = $this->createMock(CompanySegmentModel::class);
        $companyModel        = $this->createMock(CompanyModel::class);

        $eventBuilder = $this->createMock(CampaignBuilderEvent::class);
        $eventBuilder->expects(self::once())
            ->method('addAction');

        $subscriber = new CampaignSubscriber($config, $companySegmentModel, $companyModel);
        $subscriber->onCampaignBuild($eventBuilder);
    }
}
