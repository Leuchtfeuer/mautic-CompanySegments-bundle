<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Segment\Query;

use Mautic\LeadBundle\Segment\Query\LeadBatchLimiterTrait;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;

trait CompanyBatchLimiterTrait
{
    use LeadBatchLimiterTrait {
        addMinMaxLimiters as protected traitAddMinMaxLimiters;
        addLeadLimiter as protected traitAddLeadLimiter;
        addLeadAndMinMaxLimiters as protected traitLeadAndMinMaxLimiters;
    }

    /**
     * @param array<string, mixed> $batchLimiters
     */
    private function addMinMaxLimiters(QueryBuilder $queryBuilder, array $batchLimiters, string $tableName, string $columnName = 'company_id'): void
    {
        $this->traitAddMinMaxLimiters($queryBuilder, $batchLimiters, $tableName, $columnName);
    }

    private function addLeadLimiter(): void
    {
        throw new \LogicException('There are no Contacts in this flow.');
    }

    private function addLeadAndMinMaxLimiters(): void
    {
        throw new \LogicException('There are no Contacts in this flow.');
    }
}
