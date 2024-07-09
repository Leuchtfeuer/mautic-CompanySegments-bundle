<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event;

final class CompanySegmentEvents
{
    /**
     * The event is dispatched right before a company segment is persisted.
     *
     * The event listener receives a
     * MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentPreSave instance.
     */
    public const COMPANY_SEGMENT_PRE_SAVE = CompanySegmentPreSave::class;

    /**
     * The event is dispatched right after a company segment is persisted.
     *
     * The event listener receives a
     * MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentPostSave instance.
     */
    public const COMPANY_SEGMENT_POST_SAVE = CompanySegmentPostSave::class;

    /**
     * The event is dispatched right before a company segment is removed.
     *
     * The event listener receives a
     * MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentPreDelete instance.
     */
    public const COMPANY_SEGMENT_PRE_DELETE = CompanySegmentPreDelete::class;

    /**
     * The event is dispatched right after a company segment is removed.
     *
     * The event listener receives a
     * MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentPostDelete instance.
     */
    public const COMPANY_SEGMENT_POST_DELETE = CompanySegmentPostDelete::class;

    /**
     * The event is dispatched right before a company segment is unpublished.
     *
     * The event listener receives a
     * MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentPreUnpublish instance.
     */
    public const COMPANY_SEGMENT_PRE_UNPUBLISH = CompanySegmentPreUnpublish::class;

    /**
     * The event is dispatched when the choices for campaign segment filters are generated.
     *
     * The event listener receives a
     * MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentFiltersChoicesEvent instance.
     */
    public const SEGMENT_FILTERS_CHOICES_ON_GENERATE = CompanySegmentFiltersChoicesEvent::class;

    /**
     * The event is dispatched when the company is added to company segment is added.
     *
     * The event listener receives a
     * MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentAdd instance.
     */
    public const COMPANY_SEGMENT_ADD = CompanySegmentAddEvent::class;

    /**
     * The event is dispatched when the company is added to company segment is removed.
     *
     * The event listener receives a
     * MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentRemove instance.
     */
    public const COMPANY_SEGMENT_REMOVE = CompanySegmentRemoveEvent::class;

    /**
     * The event is dispatched when the lists are updated.
     *
     * The event listener receives a
     * MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\SegmentPreProcessSegmentEvent instance.
     */
    public const SEGMENT_PRE_PROCESS_SEGMENT = SegmentPreProcessSegmentEvent::class;
}
