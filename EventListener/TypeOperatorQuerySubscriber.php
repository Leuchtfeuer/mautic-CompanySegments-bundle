<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentFilteringEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TypeOperatorQuerySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CompanySegmentFilteringEvent::class => ['onCompanySegmentFiltering', 0],
        ];
    }

    /**
     * Makes sure that for datetime filters of type 'empty' and 'notEmpty' the query part
     * $queryBuilder->expr()->eq($tableAlias.'.'.$filter->getField(), $queryBuilder->expr()->literal(''))
     * will not be added as comparing datetime fields with '' can result in an error
     */
    public function onCompanySegmentFiltering(CompanySegmentFilteringEvent $event): void
    {
        $filterCrate = $event->getDetails();
        $operator = $filterCrate->getOperator();

        if (!in_array($operator, ['empty', '!empty'], true)) {
            return;
        }

        if (!$filterCrate->isCompanyType()) {
            return;
        }

        $isDateTimeField = in_array($filterCrate->getType(), ['date', 'datetime'], true);
        if (!$isDateTimeField) {
            return;
        }

        $queryBuilder = $event->getQueryBuilder();
        $tableAlias = $event->getCompaniesTableAlias();
        $field = $tableAlias.'.'.$filterCrate->getField();
        $expr = $queryBuilder->expr();

        if ('empty' === $operator) {
            $expression = $expr->isNull($field);
        } else {
            $expression = $expr->isNotNull($field);
        }

        $event->setSubQuery($expression);
    }
}