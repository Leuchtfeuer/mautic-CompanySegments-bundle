<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Support;

use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\LeuchtfeuerCompanySegmentsIntegration;

trait ActivePluginTrait
{
    private function activePlugin(bool $isPublished = true): void
    {
        $this->client->request('GET', '/s/plugins/reload');
        $nameBundle  = 'LeuchtfeuerCompanySegmentsBundle';
        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => LeuchtfeuerCompanySegmentsIntegration::NAME]);
        if (null !== $integration) {
            $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => $nameBundle]);
            $integration = new Integration();
            $integration->setName(str_replace('Bundle', '', $nameBundle));
            $integration->setPlugin($plugin);
        }
        assert($integration instanceof Integration, 'Integration should be an instance of Integration');
        $integration->setIsPublished($isPublished);
        $this->em->persist($integration);
        $this->em->flush();
    }
}
