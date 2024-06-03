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
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, ['rector.php'])).'}');

    $services->get(MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\LeuchtfeuerCompanySegmentsIntegration::class)
        ->tag('mautic.integration')
        ->tag('mautic.basic_integration');
    $services->get(MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Support\ConfigSupport::class)
        ->tag('mautic.config_integration');
    $services->load('MauticPlugin\\LeuchtfeuerCompanySegmentsBundle\\Entity\\', '../Entity/*Repository.php')
        ->tag(Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);
    $services->load('MauticPlugin\\LeuchtfeuerCompanySegmentsBundle\\DataFixtures\\ORM\\', '../DataFixtures/ORM/')
        ->tag(Doctrine\Bundle\FixturesBundle\DependencyInjection\CompilerPass\FixturesCompilerPass::FIXTURE_TAG);
    $services->alias(
        'mautic.integration.leuchtfeuercompanysegments',
        MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\LeuchtfeuerCompanySegmentsIntegration::class
    );
    $services->alias(
        'mautic.company_segments.model.company_segment',
        MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel::class
    );
};
