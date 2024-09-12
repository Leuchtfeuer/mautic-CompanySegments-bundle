<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use Symfony\Contracts\EventDispatcher\Event;

abstract class CompanySegmentRebuildChangeEvent extends Event
{
    /**
     * @param array<Company> $companies
     */
    public function __construct(
        private array $companies,
        private CompanySegment $companySegment,
        private ?\DateTime $date = null,
    ) {
    }

    /**
     * @return array<Company>
     */
    public function getCompanies(): array
    {
        return $this->companies;
    }

    public function getCompanySegment(): CompanySegment
    {
        return $this->companySegment;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }
}
