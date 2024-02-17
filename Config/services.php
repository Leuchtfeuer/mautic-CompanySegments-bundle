<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $services->load('MauticPlugin\\LeuchtfeuerCompanySegmentsBundle\\', '../')
        ->exclude('../{'.implode(',', MauticCoreExtension::DEFAULT_EXCLUDES).'}');
    $services->alias(
        'mautic.integration.leuchtfeuercompanysegments',
        MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\LeuchtfeuerCompanySegmentsIntegration::class
    );
};
