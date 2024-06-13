<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use Symfony\Contracts\EventDispatcher\Event;

class CompanySegmentRemoveEvent extends Event
{
    public function __construct(private Company $company, private CompanySegment $companySegment)
    {
    }

    public function getCompanySegment(): CompanySegment
    {
        return $this->companySegment;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }
}
