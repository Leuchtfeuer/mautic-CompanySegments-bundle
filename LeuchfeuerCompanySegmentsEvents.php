<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle;

class LeuchfeuerCompanySegmentsEvents
{
    /**
     * The event is dispatched when the company is newly added to company segment.
     */
    public const COMPANY_SEGMENT_ADD = 'leuchtfeuer_company_segment.add';

    /**
     * The event is dispatched when the company is removed from company segment.
     */
    public const COMPANY_SEGMENT_REMOVE = 'leuchtfeuer_company_segment.remove';

    public const TIMELINE_ON_GENERATE = 'leuchtfeuer_company_segment.timeline_event';
}
