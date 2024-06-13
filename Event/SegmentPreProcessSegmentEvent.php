<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;

class SegmentPreProcessSegmentEvent extends CommonEvent
{
    private bool $result = false;

    /**
     * @param array<string, int|array<mixed>> $list
     */
    public function __construct(
        protected array $list,
        bool $isNew = false
    ) {
        $this->isNew = $isNew;
    }

    /**
     * Returns the List filters.
     *
     * @return array<mixed>
     */
    public function getList(): array
    {
        return $this->list;
    }

    public function getResult(): bool
    {
        return $this->result;
    }

    public function setResult(bool $result): self
    {
        $this->result = $result;

        return $this;
    }
}
