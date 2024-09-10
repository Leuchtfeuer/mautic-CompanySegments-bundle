<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event;

use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The event is dispatched when the queryBuilder for segment was generated.
 */
class CompanySegmentQueryBuilderGeneratedEvent extends Event
{
    public function __construct(
        private CompanySegment $companySegment,
        private QueryBuilder $queryBuilder,
    ) {
    }

    public function getCompanySegment(): CompanySegment
    {
        return $this->companySegment;
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }
}
