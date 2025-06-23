<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\RouteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RouteSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::BUILD_ROUTE    => ['onBuildRoute', 0],
        ];
    }

    public function onBuildRoute(RouteEvent $event): void
    {
        $routes = $event->getCollection()->all();

        if ('main' === $event->getType() && isset($routes['mautic_company_action'])) {
            $route = $routes['mautic_company_action'];
            assert($route instanceof \Symfony\Component\Routing\Route);
            $route->setDefault('_controller', 'MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller\CompanyController::executeAction');
            $event->getCollection()->remove('mautic_company_action');
            $event->getCollection()->add('mautic_company_action', $route);
        }
    }
}
