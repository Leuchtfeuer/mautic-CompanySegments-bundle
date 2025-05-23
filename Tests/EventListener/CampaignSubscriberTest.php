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
        $companySegmentModel = $this->createMock(CompanySegmentModel::class);
        $companyModel        = $this->createMock(CompanyModel::class);
        $event               = $this->createMock(CampaignExecutionEvent::class);
        $eventBuilder        = $this->createMock(CampaignBuilderEvent::class);

        $config->method('isPublished')
            ->willReturn(false);

        $subscriber            = new CampaignSubscriber($config, $companySegmentModel, $companyModel);
        $resultOnActionTrigger = $subscriber->onCampaignActionTriggerAction($event);
        self::assertNull($resultOnActionTrigger);

        $oldEventBuilder = $eventBuilder;
        $subscriber->onCampaignBuild($eventBuilder);
        self::assertSame($oldEventBuilder, $eventBuilder);
    }
}
