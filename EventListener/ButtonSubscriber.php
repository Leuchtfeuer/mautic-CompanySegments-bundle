<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ButtonSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Config $config,
        private TranslatorInterface $translator,
        private RouterInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0],
        ];
    }

    public function injectViewButtons(CustomButtonEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        if ('mautic_company_index' !== $event->getRoute()) {
            return;
        }

        $event->addButton(
            [
                'attr' => [
                    'class'       => 'btn btn-default btn-sm btn-nospin',
                    'data-toggle' => 'ajaxmodal',
                    'data-target' => '#MauticSharedModal',
                    'href'        => $this->router->generate('mautic_company_segments_batch_company_view'),
                    'data-header' => $this->translator->trans('mautic.company_segments.action.change'),
                ],
                'btnText'   => $this->translator->trans('mautic.company_segments.action.change'),
                'iconClass' => 'ri-pie-chart-line',
            ],
            ButtonHelper::LOCATION_BULK_ACTIONS
        );
    }
}
