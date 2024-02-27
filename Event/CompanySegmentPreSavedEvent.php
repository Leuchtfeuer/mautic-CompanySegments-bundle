<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;

class CompanySegmentPreSavedEvent extends CompanySegmentEvent
{
    private bool $isNew;

    public function __construct(CompanySegment $companySegment, EntityManagerInterface $entityManager, bool $isNew)
    {
        $this->isNew = $isNew;
        parent::__construct($companySegment, $entityManager);
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }
}
