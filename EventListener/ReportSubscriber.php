<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

//use Doctrine\DBAL\Connection;
use Mautic\LeadBundle\Model\CompanyReportData;
use Mautic\ReportBundle\ReportEvents;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReportSubscriber implements EventSubscriberInterface
{
    public const CONTEXT_COMPANY_TAGS = 'company_segments';
    public const COMPANY_TABLE = 'companies';
    public const COMPANY_SEGMENTS_XREF_PREFIX = 'csx';
    public const COMPANIES_PREFIX = 'comp';

    public function __construct(
        private CompanyReportData $companyReportData,
        private CompanySegmentRepository $companySegmentRepository,
        //private Connection $db,
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
        if (!$event->checkContext([self::CONTEXT_COMPANY_TAGS])) {
            return;
        }

        $columns = $this->companyReportData->getCompanyData();
        unset($columns['companies_lead.is_primary'], $columns['companies_lead.date_added']);

        $segmentList = $this->getFilterSegments();

        $segmentFilter = [self::COMPANY_SEGMENTS_XREF_PREFIX.'.segment_id' => [
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
        ],
        ];
        $filters = array_merge($columns, $segmentFilter);

        $event->addTable(
            self::CONTEXT_COMPANY_TAGS,
            [
                'display_name' => 'mautic.company_segments.report.company_segments',
                'columns'      => $columns,
                'filters'      => $filters,
            ],
            'companies'
        );

        $event->addGraph(self::CONTEXT_COMPANY_TAGS, 'line', 'mautic.lead.graph.line.companies');
        $event->addGraph(self::CONTEXT_COMPANY_TAGS, 'pie', 'mautic.lead.graph.pie.companies.industry');
        $event->addGraph(self::CONTEXT_COMPANY_TAGS, 'pie', 'mautic.lead.table.pie.company.country');
        $event->addGraph(self::CONTEXT_COMPANY_TAGS, 'table', 'mautic.lead.company.table.top.cities');
    }

    /**
     * @return array<string, string>
     */
    public function getFilterSegments(): array
    {
        $segments    = $this->companySegmentRepository->getSegmentObjectsViaListOfIDs([]);
        $segmentList = [];
        foreach ($segments as $segment) {
            $segmentList[(string) $segment->getId()] = (string) $segment->getName();
        }

        return $segmentList;
    }

    public function onReportGenerate(ReportGeneratorEvent $event): void
    {
        $qb = $event->getQueryBuilder();
        $qb
            ->from(MAUTIC_TABLE_PREFIX . self::COMPANY_TABLE, self::COMPANIES_PREFIX);
    }
}