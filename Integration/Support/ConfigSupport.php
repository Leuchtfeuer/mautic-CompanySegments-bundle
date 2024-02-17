<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\LeuchtfeuerCompanySegmentsIntegration;

class ConfigSupport extends LeuchtfeuerCompanySegmentsIntegration implements ConfigFormInterface
{
    use DefaultConfigFormTrait;
}
