<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\IntegrationsBundle\Integration\Interfaces\IntegrationInterface;
use Mautic\PluginBundle\Facade\ReloadFacade;
use Mautic\PluginBundle\Helper\IntegrationHelper;

trait EnablePluginTrait
{
    private function enablePlugin(bool $enable): void
    {
        $pluginInstaller = self::getContainer()->get(ReloadFacade::class);
        assert($pluginInstaller instanceof ReloadFacade);
        $pluginInstaller->reloadPlugins();
        $integrationHelper = self::getContainer()->get(IntegrationHelper::class);
        assert($integrationHelper instanceof IntegrationHelper);
        $integration = $integrationHelper->getIntegrationObject('LeuchtfeuerCompanySegments');
        assert($integration instanceof IntegrationInterface);
        $integration->getIntegrationConfiguration()->setIsPublished($enable);
        $doctrine = self::getContainer()->get('doctrine');
        assert($doctrine instanceof ManagerRegistry);
        $doctrine->getManager()->flush();
    }
}
