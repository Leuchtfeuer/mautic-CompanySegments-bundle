<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\LeadBundle\Model\CompanyReportData;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;
use Mautic\ReportBundle\ReportEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReportSubscriber implements EventSubscriberInterface
{
    public const CONTEXT_COMPANY_SEGMENTS         = 'company_segments';
    public const COMPANY_TABLE                    = 'companies';
    public const COMPANIES_PREFIX                 = 'comp';
    public const COMPANY_SEGMENTS_XREF_PREFIX     = 'csx';
    public const COMPANY_SEGMENTS_XREF_TABLE      = CompaniesSegments::TABLE_NAME;

    public function __construct(
        private CompanyReportData $companyReportData,
        private CompanySegmentRepository $companySegmentRepository,
        private Connection $db,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReportEvents::REPORT_ON_BUILD    => ['onReportBuilder', 0],
            ReportEvents::REPORT_ON_GENERATE => ['onReportGenerate', 0],
        ];
    }

    public function onReportBuilder(ReportBuilderEvent $event): void
    {
        if (!$event->checkContext([self::CONTEXT_COMPANY_SEGMENTS])) {
            return;
        }

        $keys = [
            'comp.id',
            'comp.companyaddress1',
            'comp.companyaddress2',
            'comp.companyemail',
            'comp.companyphone',
            'comp.companycity',
            'comp.companystate',
            'comp.companyzipcode',
            'comp.companycountry',
            'comp.companyname',
            'comp.companywebsite',
            'comp.companynumber_of_employees',
            'comp.companyfax',
            'comp.companyannual_revenue',
            'comp.companyindustry',
            'comp.companydescription',
        ];
        $columns         = $this->companyReportData->getCompanyData();
        $filteredColumns = array_intersect_key($columns, array_flip($keys));

        $segmentList = $this->getFilterSegments();

        $filters                                                   = $filteredColumns;
        $filters[self::COMPANY_SEGMENTS_XREF_PREFIX.'.segment_id'] = [
            'alias'     => 'companysegments',
            'label'     => 'mautic.company_segments.report.company_segments',
            'type'      => 'select',
            'list'      => $segmentList,
            'operators' => [
                'in'       => 'mautic.core.operator.in',
                'notIn'    => 'mautic.core.operator.notin',
                'empty'    => 'mautic.core.operator.isempty',
                'notEmpty' => 'mautic.core.operator.isnotempty',
            ],
        ];

        $event->addTable(
            self::CONTEXT_COMPANY_SEGMENTS,
            [
                'display_name' => 'mautic.company_segments.report.company_segments',
                'columns'      => $filteredColumns,
                'filters'      => $filters,
            ],
            'companies'
        );
    }

    /**
     * @return array<string, string>
     */
    public function getFilterSegments(): array
    {
        $segments    = $this->companySegmentRepository->getSegmentObjectsViaListOfIDs();
        $segmentList = [];
        foreach ($segments as $segment) {
            $segmentList[(string) $segment->getId()] = (string) $segment->getName();
        }

        return $segmentList;
    }

    /**
     * Most of the code in this function was duplicated from the MauticReportBuilder as this option was preferred to making a patch.
     */
    public function onReportGenerate(ReportGeneratorEvent $event): void
    {
        if (!$event->checkContext([self::CONTEXT_COMPANY_SEGMENTS])) {
            return;
        }

        $qb       = $event->getQueryBuilder();
        $filters  = $event->getReport()->getFilters();
        $options  = $event->getOptions()['columns'];
        $orGroups = [];
        $andGroup = [];

        $qb
            ->from(MAUTIC_TABLE_PREFIX.self::COMPANY_TABLE, self::COMPANIES_PREFIX);

        $expr     = $qb->expr();

        if (count($filters) > 0) {
            foreach ($filters as $i => $filter) {
                $exprFunction = $filter['expr'] ?? $filter['condition'];
                $paramName    = sprintf('i%dc%s', $i, InputHelper::alphanum($filter['column']));

                if (array_key_exists('glue', $filter) && 'or' === $filter['glue']) {
                    $orGroups[] = CompositeExpression::and(...$andGroup);
                    $andGroup   = [];
                }

                $companySegmentCondition = $this->getCompanySegmentCondition($filter);
                if (!is_null($companySegmentCondition)) {
                    $andGroup[] = $companySegmentCondition;
                    continue;
                }
                switch ($exprFunction) {
                    case 'notEmpty':
                        $andGroup[] = $expr->isNotNull($filter['column']);
                        if ($this->doesColumnSupportEmptyValue($filter, $options)) {
                            $andGroup[] = $expr->neq($filter['column'], $expr->literal(''));
                        }
                        break;
                    case 'empty':
                        $expression = $qb->expr()->or(
                            $qb->expr()->isNull($filter['column'])
                        );
                        if ($this->doesColumnSupportEmptyValue($filter, $options)) {
                            $expression = $expression->with(
                                $qb->expr()->eq($filter['column'], $expr->literal(''))
                            );
                        }

                        $andGroup[] = $expression;
                        break;
                    case 'neq':
                        $columnValue = ":$paramName";
                        $expression  = $qb->expr()->or(
                            $qb->expr()->isNull($filter['column']),
                            /** @phpstan-ignore-next-line */
                            $qb->expr()->$exprFunction($filter['column'], $columnValue)
                        );
                        $qb->setParameter($paramName, $filter['value']);
                        $andGroup[] = $expression;
                        break;
                    default:
                        if ('' == trim($filter['value'])) {
                            // Ignore empty
                            break;
                        }

                        $columnValue = ":$paramName";
                        $type        = $options[$filter['column']]['type'];
                        if (isset($options[$filter['column']]['formula'])) {
                            $filter['column'] = $options[$filter['column']]['formula'];
                        }

                        switch ($type) {
                            case 'bool':
                            case 'boolean':
                                if ((int) $filter['value'] > 1) {
                                    // Ignore the "reset" value of "2"
                                    break 2;
                                }

                                $qb->setParameter($paramName, $filter['value'], 'boolean');
                                break;

                            case 'float':
                                $columnValue = (float) $filter['value'];
                                break;

                            case 'int':
                            case 'integer':
                                $columnValue = (int) $filter['value'];
                                break;

                            case 'string':
                            case 'email':
                            case 'url':
                                switch ($exprFunction) {
                                    case 'like':
                                    case 'notLike':
                                        $filter['value'] = !str_contains($filter['value'], '%') ? '%'.$filter['value'].'%' : $filter['value'];
                                        break;
                                    case 'startsWith':
                                        $exprFunction    = 'like';
                                        $filter['value'] = $filter['value'].'%';
                                        break;
                                    case 'endsWith':
                                        $exprFunction    = 'like';
                                        $filter['value'] = '%'.$filter['value'];
                                        break;
                                    case 'contains':
                                        $exprFunction    = 'like';
                                        $filter['value'] = '%'.$filter['value'].'%';
                                        break;
                                }

                                $qb->setParameter($paramName, $filter['value']);
                                break;

                            default:
                                $qb->setParameter($paramName, $filter['value']);
                        }
                        /** @phpstan-ignore-next-line */
                        $andGroup[] = $expr->{$exprFunction}($filter['column'], $columnValue);
                }
            }
        }

        if (boolval($orGroups)) {
            // Add the remaining $andGroup to the rest of the $orGroups if exists so we don't miss it.
            $orGroups[] = CompositeExpression::and(...$andGroup);
            $qb->andWhere(CompositeExpression::or(...$orGroups));
        } elseif (boolval($andGroup)) {
            $qb->andWhere(CompositeExpression::and(...$andGroup));
        }

        $event->getReport()->setFilters([]);
    }

    /**
     * @param array<string, string|null> $filter
     */
    public function getCompanySegmentCondition(array $filter): ?string
    {
        if (!$this->checkIfCompanySegmentFilter($filter)) {
            return null;
        }

        $segmentSubQuery = $this->prepareSegmentSubQuery();

        return $this->finalizeSubQuery($segmentSubQuery, $filter);
    }

    /**
     * @param array<string, string|null> $filter
     */
    private function checkIfCompanySegmentFilter(array $filter): bool
    {
        return self::COMPANY_SEGMENTS_XREF_PREFIX.'.segment_id' === $filter['column'];
    }

    private function prepareSegmentSubQuery(): QueryBuilder
    {
        return $this->db->createQueryBuilder()->select('DISTINCT '.self::COMPANY_SEGMENTS_XREF_PREFIX.'.company_id')
            ->from(MAUTIC_TABLE_PREFIX.self::COMPANY_SEGMENTS_XREF_TABLE, self::COMPANY_SEGMENTS_XREF_PREFIX);
    }

    /**
     * @param array<string, string|null> $filter
     */
    private function finalizeSubQuery(QueryBuilder $segmentSubQuery, array $filter): string
    {
        if (in_array($filter['condition'], ['in', 'notIn'], true) && !is_null($filter['value'])) {
            $segmentSubQuery->andWhere($segmentSubQuery->expr()->in(self::COMPANY_SEGMENTS_XREF_PREFIX.'.segment_id', $filter['value']));
        }

        $subQuery = $segmentSubQuery->getSQL();

        if (in_array($filter['condition'], ['in', 'notEmpty'], true)) {
            return $segmentSubQuery->expr()->in(self::COMPANIES_PREFIX.'.id', '('.$subQuery.')');
        } elseif (in_array($filter['condition'], ['notIn', 'empty'], true)) {
            return $segmentSubQuery->expr()->notIn(self::COMPANIES_PREFIX.'.id', '('.$subQuery.')');
        }

        throw new \InvalidArgumentException('Invalid filter condition');
    }

    /**
     * This code was duplicated from the MauticReportBuilder as this option was preferred to making a patch.
     *
     * @param array<string, mixed>                 $filter
     * @param array<string, array<string, string>> $filterDefinitions
     */
    private function doesColumnSupportEmptyValue(array $filter, array $filterDefinitions): bool
    {
        $type = $filterDefinitions[$filter['column']]['type'] ?? null;

        return !in_array($type, ['date', 'datetime'], true);
    }
}
