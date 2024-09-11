<?php

namespace EventListener;

use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Query\QueryBuilder;
use Mautic\ReportBundle\Entity\Report;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener\ReportSubscriber;
use Mautic\LeadBundle\Model\CompanyReportData;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;
use Mautic\ChannelBundle\Helper\ChannelListHelper;
use Mautic\ReportBundle\Helper\ReportHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ReportSubscriberTest extends TestCase
{
    private ReportSubscriber $reportSubscriber;
    private MockObject $reportBuilderEventMock;
    private MockObject $reportGeneratorEventMock;
    private MockObject $companyReportDataMock;
    private MockObject $companySegmentRepositoryMock;
    private MockObject $dbMock;
    private MockObject $translatorMock;
    private MockObject $channelListHelperMock;
    private ReportHelper $reportHelper;
    private MockObject $eventDispatcherMock;
    private MockObject $queryBuilderMock;
    private MockObject $reportMock;

    private array $columns;
    private array $options;

    protected function setUp(): void
    {
        $this->columns = [
            'comp.id' => [
                'alias' => 'comp_id',
                'label' => 'mautic.lead.report.company.company_id',
                'type' => 'int',
                'link' => 'mautic_company_action'
            ],
            'companies_lead.is_primary' => [
                'label' => 'mautic.lead.report.company.is_primary',
                'type' => 'bool'
            ],
            'companies_lead.date_added' => [
                'label' => 'mautic.lead.report.company.date_added',
                'type' => 'datetime'
            ],
            'comp.companyaddress1' => [
                'label' => 'Company Address 1',
                'type' => 'string'
            ],
            'comp.companyaddress2' => [
                'label' => 'Company Address 2',
                'type' => 'string'
            ],
            'comp.companyemail' => [
                'label' => 'Company Company Email',
                'type' => 'email'
            ],
            'comp.companyphone' => [
                'label' => 'Company Phone',
                'type' => 'string'
            ],
            'comp.companycity' => [
                'label' => 'Company City',
                'type' => 'string'
            ],
            'comp.companystate' => [
                'label' => 'Company State',
                'type' => 'string'
            ],
            'comp.companyzipcode' => [
                'label' => 'Company Zip Code',
                'type' => 'string'
            ],
            'comp.companycountry' => [
                'label' => 'Company Country',
                'type' => 'string'
            ],
            'comp.companyname' => [
                'label' => 'Company Company Name',
                'type' => 'string'
            ],
            'comp.companywebsite' => [
                'label' => 'Company Website',
                'type' => 'url'
            ],
            'comp.companynumber_of_employees' => [
                'label' => 'Company Number of Employees',
                'type' => 'float'
            ],
            'comp.companyfax' => [
                'label' => 'Company Fax',
                'type' => 'string'
            ],
            'comp.companyannual_revenue' => [
                'label' => 'Company Annual Revenue',
                'type' => 'float'
            ],
            'comp.companyindustry' => [
                'label' => 'Company Industry',
                'type' => 'string'
            ],
            'comp.companydescription' => [
                'label' => 'Company Description',
                'type' => 'string'
            ]
        ];

        $this->options = [
            "order" => false,
            $this->columns,
            "filters" => array_merge(
                array_values($this->columns),
                [
                    "csx.segment_id" => [
                        "alias" => "companysegments",
                        "label" => "Company Segments",
                        "type" => "select",
                        "list" => [],
                        "operators" => []
                    ]
                ]
            ),
            /*
            "dateFrom" => new DateTime('2024-08-11 00:00:00', new DateTimeZone('UTC')),
            "dateTo" => new DateTime('2024-09-11 11:17:05', new DateTimeZone('UTC')),
            */
            "dynamicFilters" => []
        ];

        $this->companyReportDataMock = $this->createMock(CompanyReportData::class);
        $this->companySegmentRepositoryMock = $this->createMock(CompanySegmentRepository::class);
        $this->dbMock = $this->createMock(Connection::class);
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->channelListHelperMock = $this->createMock(ChannelListHelper::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->queryBuilderMock = $this->createMock(QueryBuilder::class);
        $this->reportMock = $this->createMock(Report::class);


        $this->reportHelper = new ReportHelper($this->eventDispatcherMock);

        $this->reportGeneratorEventMock = $this->getMockBuilder(ReportGeneratorEvent::class)
            ->setConstructorArgs([
                $this->reportMock,
                $this->options,
                $this->queryBuilderMock,
                $this->channelListHelperMock,
            ])
            ->onlyMethods(['checkContext'])
            ->getMock();


        $this->reportBuilderEventMock = $this->getMockBuilder(ReportBuilderEvent::class)
            ->setConstructorArgs([
                $this->translatorMock,
                $this->channelListHelperMock,
                'company_segments', // context
                [],
                $this->reportHelper,
                null
            ])
            ->onlyMethods(['checkContext'])
            ->getMock();

        $this->reportSubscriber = new ReportSubscriber(
            $this->companyReportDataMock,
            $this->companySegmentRepositoryMock,
            $this->dbMock
        );
    }

    private function createFakeCompanySegments(): array
    {
        $segments = [];
        for ($i = 1; $i <= 4; $i++) {
            $segment = $this->createMock(\MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment::class);
            $segment->method('getId')->willReturn($i);
            $segment->method('getName')->willReturn(chr(96 + $i)); // 'a', 'b', 'c', 'd'
            $segments[$i] = $segment;
        }
        return $segments;
    }

    public function testOnReportBuilderAddsCompanySegmentsToReportWithCorrectColumnsAndFilters()
    {
        $this->reportBuilderEventMock->expects($this->once())
            ->method('checkContext')
            ->willReturn(true);

        $this->companyReportDataMock->expects($this->once())
            ->method('getCompanyData')
            ->willReturn($this->columns);

        $fakeSegments = $this->createFakeCompanySegments();
        $this->companySegmentRepositoryMock->expects($this->once())
            ->method('getSegmentObjectsViaListOfIDs')
            ->willReturn($fakeSegments);

        $this->reportSubscriber->onReportBuilder($this->reportBuilderEventMock);
        $tables = $this->reportBuilderEventMock->getTables();

        $this->assertArrayHasKey('company_segments', $tables);
        $this->assertCount(16, $tables['company_segments']['columns']);
        $this->assertCount(17, $tables['company_segments']['filters']);

        $segmentFilter = $tables['company_segments']['filters']['csx.segment_id'];
        $this->assertIsArray($segmentFilter);
        $this->assertArrayHasKey('list', $segmentFilter);
        $this->assertCount(4, $segmentFilter['list']);
        $this->assertEquals(['1' => 'a', '2' => 'b', '3' => 'c', '4' => 'd'], $segmentFilter['list']);
    }

    public function testOnReportBuilderHandlesEmptySegmentListCorrectly()
    {
        $this->reportBuilderEventMock->expects($this->once())
            ->method('checkContext')
            ->willReturn(true);

        $this->companyReportDataMock->expects($this->once())
            ->method('getCompanyData')
            ->willReturn($this->columns);

        $this->companySegmentRepositoryMock->expects($this->once())
            ->method('getSegmentObjectsViaListOfIDs')
            ->willReturn([]);

        $this->reportSubscriber->onReportBuilder($this->reportBuilderEventMock);
        $tables = $this->reportBuilderEventMock->getTables();
        $segmentFilter = $tables['company_segments']['filters']['csx.segment_id'];

        $this->assertArrayHasKey('company_segments', $tables);
        $this->assertCount(16, $tables['company_segments']['columns']);
        $this->assertCount(17, $tables['company_segments']['filters']);

        $this->assertIsArray($segmentFilter);
        $this->assertArrayHasKey('list', $segmentFilter);
        $this->assertEmpty($segmentFilter['list']);

        $this->assertEquals('select', $segmentFilter['type']);
        $this->assertArrayHasKey('operators', $segmentFilter);
        $this->assertCount(4, $segmentFilter['operators']);
    }


    public function OnReportBuilderWithWrongContext(){
        $this->reportBuilderEventMock->expects($this->once())
            ->method('checkContext')
            ->willReturn(false);
    }

}