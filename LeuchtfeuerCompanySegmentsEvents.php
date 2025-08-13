<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle;

final class LeuchtfeuerCompanySegmentsEvents
{
    /**
     * This event is triggered when a company segment is created.
     *
     * @var string
     */
    public const MANAGE_COMPANY_SEGMENT_EVENT = 'plugin.company_segments.manage_company_segment_event';

    public const ON_CAMPAIGN_TRIGGER_CONDITION = 'mautic.company_segments.on_campaign_trigger_condition';
}
