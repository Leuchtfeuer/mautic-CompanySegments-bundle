<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event;

/**
 * The event is dispatched right after a company segment is persisted.
 */
class CompanySegmentPostSave extends CompanySegmentPreSavedEvent
{
    /**
     * @return array<string, mixed>
     */
    public function getChanges(): array
    {
        return $this->getCompanySegment()->getChanges();
    }
}
