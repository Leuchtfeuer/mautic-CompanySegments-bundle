<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyModelDecorated;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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

    $services->alias(
        'mautic.company_segments.model.company_event_log',
        MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel::class
    );

    $services->set(CompanyModelDecorated::class)
        ->decorate(CompanyModel::class);

    $services->set(MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyFieldModelDecorated::class)
        ->decorate(Mautic\LeadBundle\Model\FieldModel::class);

    $services->set(MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type\DwcEntryFiltersTypeDecorator::class)
        ->decorate('mautic.form.type.dwc_entry_filters', null, 10)
        ->args([
            service('translator'),
            service('mautic.lead.model.list'),
            service('mautic.company_segments.model.company_segment'),
        ])
        ->call('setConnection', [service('database_connection')])
        ->tag('form.type');

    $services->set(MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type\DcEntryFiltersTypeDecorator::class)
        ->decorate('mautic.form.type.dynamic_content_filter_entry_filters', null, 10)
        ->args([
            service('translator'),
            service('mautic.lead.model.list'),
            service('mautic.company_segments.model.company_segment'),
        ])
        ->call('setConnection', [service('database_connection')])
        ->tag('form.type');
};
