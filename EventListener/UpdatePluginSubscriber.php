<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\PluginBundle\Bundle\PluginBundleBase;
use Mautic\PluginBundle\Event\PluginUpdateEvent;
use Mautic\PluginBundle\Model\PluginModel;
use Mautic\PluginBundle\PluginEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UpdatePluginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PluginModel $pluginModel,
        private MauticFactory $factory,
    ) {
        // Constructor logic if needed
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::ON_PLUGIN_UPDATE => 'onPluginUpdate',
        ];
    }

    public function onPluginUpdate(PluginUpdateEvent $event): void
    {
        $pluginMetadata          = $this->pluginModel->getPluginsMetadata();

        $companyEventLogMetadata = $pluginMetadata['MauticPlugin\LeuchtfeuerCompanySegmentsBundle'];
        $tableInstalled          =$this->pluginModel->getInstalledPluginTables($pluginMetadata);
        if (empty($tableInstalled)) {
            // No tables installed, so we can install the schema
            PluginBundleBase::installPluginSchema($companyEventLogMetadata, $this->factory);

            return;
        }
        if (isset($tableInstalled['MauticPlugin\LeuchtfeuerCompanySegmentsBundle'])) {
            foreach ($tableInstalled['MauticPlugin\LeuchtfeuerCompanySegmentsBundle'] as $table) {
                assert($table instanceof \Doctrine\DBAL\Schema\Table);
                foreach ($companyEventLogMetadata as $entityName => $metadata) {
                    if ($table->getName() === $metadata->getTableName()) {
                        unset($companyEventLogMetadata[$entityName]);
                    }
                }
            }
        }
        // work here to check if schema is already installed
        PluginBundleBase::installPluginSchema($companyEventLogMetadata, $this->factory);
    }
}
