<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EventListener;

use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener\ButtonSubscriber;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ButtonSubscriberTest extends TestCase
{
    public function testNotPublishedIsNotExecuted(): void
    {
        $config     = $this->createMock(Config::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $router     = $this->createMock(RouterInterface::class);

        $config->method('isPublished')
            ->willReturn(false);

        $event = $this->createMock(CustomButtonEvent::class);
        $event->expects(self::never())
            ->method('getRoute');
        $event->expects(self::never())
            ->method('addButton');

        $subscriber = new ButtonSubscriber($config, $translator, $router);
        $subscriber->injectViewButtons($event);
    }

    public function testSubscriberIsExecutedOnNotProperRoute(): void
    {
        $config     = $this->createMock(Config::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $router     = $this->createMock(RouterInterface::class);

        $config->method('isPublished')
            ->willReturn(true);

        $event = $this->createMock(CustomButtonEvent::class);
        $event->expects(self::once())
            ->method('getRoute')
            ->willReturn('other_route');
        $event->expects(self::never())
            ->method('addButton');

        $subscriber = new ButtonSubscriber($config, $translator, $router);
        $subscriber->injectViewButtons($event);
    }

    public function testSubscriberIsExecutedOnProperRoute(): void
    {
        $config     = $this->createMock(Config::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $router     = $this->createMock(RouterInterface::class);

        $config->method('isPublished')
            ->willReturn(true);

        $event = $this->createMock(CustomButtonEvent::class);
        $event->expects(self::once())
            ->method('getRoute')
            ->willReturn('mautic_company_index');
        $event->expects(self::once())
            ->method('addButton')
            ->with(
                [
                    'attr' => [
                        'class'       => 'btn btn-default btn-sm btn-nospin',
                        'data-toggle' => 'ajaxmodal',
                        'data-target' => '#MauticSharedModal',
                        'href'        => $router->generate('mautic_company_segments_batch_company_view'),
                        'data-header' => $translator->trans('mautic.company_segments.action.change'),
                    ],
                    'btnText'   => $translator->trans('mautic.company_segments.action.change'),
                    'iconClass' => 'ri-pie-chart-line',
                ],
                ButtonHelper::LOCATION_BULK_ACTIONS
            );

        $subscriber = new ButtonSubscriber($config, $translator, $router);
        $subscriber->injectViewButtons($event);
    }
}
