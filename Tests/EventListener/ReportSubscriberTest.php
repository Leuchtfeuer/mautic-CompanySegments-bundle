<?php

namespace EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Mautic\ChannelBundle\Helper\ChannelListHelper;
use Mautic\LeadBundle\Model\CompanyReportData;
use Mautic\LeadBundle\Segment\Query\Expression\ExpressionBuilder;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Helper\ReportHelper;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener\ReportSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReportSubscriberTest extends TestCase
{
    private ReportSubscriber $reportSubscriber;
    /** @var MockObject&ReportBuilderEvent */
    private MockObject $reportBuilderEventMock;
    /** @var MockObject&CompanyReportData */
    private MockObject $companyReportDataMock;
    /** @var MockObject&CompanySegmentRepository */
    private MockObject $companySegmentRepositoryMock;
    /** @var MockObject&Connection */
    private MockObject $dbMock;
    /** @var MockObject&TranslatorInterface */
    private MockObject $translatorMock;
    /** @var MockObject&ChannelListHelper */
    private MockObject $channelListHelperMock;
    private ReportHelper $reportHelper;
    /** @var MockObject&EventDispatcherInterface */
    private MockObject $eventDispatcherMock;
    /** @var MockObject&QueryBuilder */
    private MockObject $queryBuilderMock;
    /** @var MockObject&ExpressionBuilder */
    private MockObject $exprMock;

    /**
     * @var array<string, array<string, string>>
     */
    private array $columns;

    protected function setUp(): void
    {
        $this->columns = [
            'comp.id' => [
                'alias' => 'comp_id',
                'label' => 'mautic.lead.report.company.company_id',
                'type'  => 'int',
                'link'  => 'mautic_company_action',
            ],
            'companies_lead.is_primary' => [
                'label' => 'mautic.lead.report.company.is_primary',
                'type'  => 'bool',
            ],
            'companies_lead.date_added' => [
                'label' => 'mautic.lead.report.company.date_added',
                'type'  => 'datetime',
            ],
            'comp.companyaddress1' => [
                'label' => 'Company Address 1',
                'type'  => 'string',
            ],
            'comp.companyaddress2' => [
                'label' => 'Company Address 2',
                'type'  => 'string',
            ],
            'comp.companyemail' => [
                'label' => 'Company Company Email',
                'type'  => 'email',
            ],
            'comp.companyphone' => [
                'label' => 'Company Phone',
                'type'  => 'string',
            ],
            'comp.companycity' => [
                'label' => 'Company City',
                'type'  => 'string',
            ],
            'comp.companystate' => [
                'label' => 'Company State',
                'type'  => 'string',
            ],
            'comp.companyzipcode' => [
                'label' => 'Company Zip Code',
                'type'  => 'string',
            ],
            'comp.companycountry' => [
                'label' => 'Company Country',
                'type'  => 'string',
            ],
            'comp.companyname' => [
                'label' => 'Company Company Name',
                'type'  => 'string',
            ],
            'comp.companywebsite' => [
                'label' => 'Company Website',
                'type'  => 'url',
            ],
            'comp.companynumber_of_employees' => [
                'label' => 'Company Number of Employees',
                'type'  => 'float',
            ],
            'comp.companyfax' => [
                'label' => 'Company Fax',
                'type'  => 'string',
            ],
            'comp.companyannual_revenue' => [
                'label' => 'Company Annual Revenue',
                'type'  => 'float',
            ],
            'comp.companyindustry' => [
                'label' => 'Company Industry',
                'type'  => 'string',
            ],
            'comp.companydescription' => [
                'label' => 'Company Description',
                'type'  => 'string',
            ],
        ];

        $this->companyReportDataMock        = $this->createMock(CompanyReportData::class);
        $this->companySegmentRepositoryMock = $this->createMock(CompanySegmentRepository::class);
        $this->dbMock                       = $this->createMock(Connection::class);
        $this->translatorMock               = $this->createMock(TranslatorInterface::class);
        $this->channelListHelperMock        = $this->createMock(ChannelListHelper::class);
        $this->eventDispatcherMock          = $this->createMock(EventDispatcherInterface::class);
        $this->queryBuilderMock             = $this->createMock(QueryBuilder::class);
        $this->queryBuilderMock->method('select')->willReturnSelf();
        $this->queryBuilderMock->method('from')->willReturnSelf();
        $this->queryBuilderMock->method('andWhere')->willReturnSelf();
        $this->exprMock = $this->createMock(ExpressionBuilder::class);
        $this->queryBuilderMock->method('expr')->willReturn($this->exprMock);

        $this->reportHelper = new ReportHelper($this->eventDispatcherMock);

        $this->reportBuilderEventMock = $this->getMockBuilder(ReportBuilderEvent::class)
            ->setConstructorArgs([
                $this->translatorMock,
                $this->channelListHelperMock,
                'company_segments', // context
                [],
                $this->reportHelper,
                null,
            ])
            ->onlyMethods(['checkContext'])
            ->getMock();

        // Set up the dbMock to return the queryBuilderMock
        $this->dbMock->method('createQueryBuilder')->willReturn($this->queryBuilderMock);

        $this->reportSubscriber = new ReportSubscriber(
            $this->companyReportDataMock,
            $this->companySegmentRepositoryMock,
            $this->dbMock
        );
    }

    /**
     * @return array<int, CompanySegment>
     */
    private function createFakeCompanySegments(): array
    {
        $segments = [];
        for ($i = 1; $i <= 4; ++$i) {
            $segment = $this->createMock(CompanySegment::class);
            $segment->method('getId')->willReturn($i);
            $segment->method('getName')->willReturn(chr(96 + $i)); // 'a', 'b', 'c', 'd'
            $segments[$i] = $segment;
        }

        return $segments;
    }

    public function testOnReportBuilderAddsCompanySegmentsToReportWithCorrectColumnsAndFilters(): void
    {
        $this->reportBuilderEventMock->expects(self::once())
            ->method('checkContext')
            ->willReturn(true);

        $this->companyReportDataMock->expects(self::once())
            ->method('getCompanyData')
            ->willReturn($this->columns);

        $fakeSegments = $this->createFakeCompanySegments();
        $this->companySegmentRepositoryMock->expects(self::once())
            ->method('getSegmentObjectsViaListOfIDs')
            ->willReturn($fakeSegments);

        $this->reportSubscriber->onReportBuilder($this->reportBuilderEventMock);
        $tables = $this->reportBuilderEventMock->getTables();

        self::assertArrayHasKey('company_segments', $tables);
        self::assertCount(16, $tables['company_segments']['columns']);
        self::assertCount(17, $tables['company_segments']['filters']);

        $segmentFilter = $tables['company_segments']['filters'][ReportSubscriber::COMPANY_SEGMENTS_XREF_PREFIX.'.segment_id'];
        self::assertIsArray($segmentFilter);
        self::assertArrayHasKey('list', $segmentFilter);
        self::assertCount(4, $segmentFilter['list']);
        self::assertEquals(['1' => 'a', '2' => 'b', '3' => 'c', '4' => 'd'], $segmentFilter['list']);
    }

    public function testOnReportBuilderHandlesEmptySegmentListCorrectly(): void
    {
        $this->reportBuilderEventMock->expects(self::once())
            ->method('checkContext')
            ->willReturn(true);

        $this->companyReportDataMock->expects(self::once())
            ->method('getCompanyData')
            ->willReturn($this->columns);

        $this->companySegmentRepositoryMock->expects(self::once())
            ->method('getSegmentObjectsViaListOfIDs')
            ->willReturn([]);

        $this->reportSubscriber->onReportBuilder($this->reportBuilderEventMock);
        $tables        = $this->reportBuilderEventMock->getTables();
        $segmentFilter = $tables['company_segments']['filters'][ReportSubscriber::COMPANY_SEGMENTS_XREF_PREFIX.'.segment_id'];

        self::assertArrayHasKey('company_segments', $tables);
        self::assertCount(16, $tables['company_segments']['columns']);
        self::assertCount(17, $tables['company_segments']['filters']);

        self::assertIsArray($segmentFilter);
        self::assertArrayHasKey('list', $segmentFilter);
        self::assertEmpty($segmentFilter['list']);

        self::assertEquals('select', $segmentFilter['type']);
        self::assertArrayHasKey('operators', $segmentFilter);
        self::assertCount(4, $segmentFilter['operators']);
    }

    public function testOnReportBuilderWithWrongContext(): void
    {
        $this->reportBuilderEventMock->expects(self::once())
            ->method('checkContext')
            ->willReturn(false);
        $this->reportSubscriber->onReportBuilder($this->reportBuilderEventMock);
    }

    public function testGetCompanySegmentConditionWrongFilterColumn(): void
    {
        $filter = [
            'column' => 'abcd',
        ];

        $result = $this->reportSubscriber->getCompanySegmentCondition($filter);
        self::assertEquals(null, $result);
    }

    public function testGetCompanySegmentConditionWhenOperatorEqualsIn(): void
    {
        $filterSubQueryResult = 'filter sub query';
        $filterResult         = 'filter result';
        $subQueryResult       = 'sub query';
        $filterValue          = '3';

        $this->queryBuilderMock->expects(self::once())
            ->method('andWhere')
            ->with($filterSubQueryResult);
        $this->queryBuilderMock->expects(self::once())
            ->method('getSQL')
            ->willReturn($subQueryResult);

        $this->exprMock
            ->expects(self::exactly(2))
            ->method('in')
            ->willReturnCallback(static function (string $field, string $value) use ($subQueryResult, $filterValue, $filterResult, $filterSubQueryResult): string {
                if ($field === ReportSubscriber::COMPANY_SEGMENTS_XREF_PREFIX.'.segment_id') {
                    self::assertSame($filterValue, $value);

                    return $filterSubQueryResult;
                }

                if ($field === ReportSubscriber::COMPANIES_PREFIX.'.id') {
                    self::assertSame('('.$subQueryResult.')', $value);

                    return $filterResult;
                }

                self::fail('Unknown field: '.$field);
            });

        $filter = [
            'column'    => ReportSubscriber::COMPANY_SEGMENTS_XREF_PREFIX.'.segment_id',
            'glue'      => 'and',
            'dynamic'   => null,
            'condition' => 'in',
            'value'     => $filterValue,
        ];

        self::assertSame($filterResult, $this->reportSubscriber->getCompanySegmentCondition($filter));
    }

    public function testGetCompanySegmentConditionWhenOperatorEqualsNotIn(): void
    {
        $filterSubQueryResult = 'filter sub query';
        $filterResult         = 'filter result';
        $subQueryResult       = 'sub query';
        $filterValue          = '3';

        $this->queryBuilderMock->expects(self::once())
            ->method('andWhere')
            ->with($filterSubQueryResult);
        $this->queryBuilderMock->expects(self::once())
            ->method('getSQL')
            ->willReturn($subQueryResult);

        $this->exprMock
            ->expects(self::once())
            ->method('in')
            ->willReturnCallback(static function (string $field, string $value) use ($filterValue, $filterSubQueryResult): string {
                self::assertSame($filterValue, $value);

                return $filterSubQueryResult;
            });

        $this->exprMock
            ->expects(self::once())
            ->method('notIn')
            ->willReturnCallback(static function (string $field, string $value) use ($subQueryResult, $filterResult): string {
                self::assertSame('('.$subQueryResult.')', $value);

                return $filterResult;
            });

        $filter = [
            'column'    => ReportSubscriber::COMPANY_SEGMENTS_XREF_PREFIX.'.segment_id',
            'glue'      => 'and',
            'dynamic'   => null,
            'condition' => 'notIn',
            'value'     => '3',
        ];

        self::assertSame($filterResult, $this->reportSubscriber->getCompanySegmentCondition($filter));
    }

    public function testGetCompanySegmentConditionWhenOperatorEqualsEmpty(): void
    {
        $filterResult         = 'filter result';
        $subQueryResult       = 'sub query';
        $this->queryBuilderMock->expects(self::once())
            ->method('getSQL')
            ->willReturn($subQueryResult);

        $this->exprMock
            ->expects(self::once())
            ->method('notIn')
            ->willReturnCallback(static function (string $field, string $value) use ($subQueryResult, $filterResult): string {
                self::assertSame('('.$subQueryResult.')', $value);

                return $filterResult;
            });

        $filter = [
            'column'    => ReportSubscriber::COMPANY_SEGMENTS_XREF_PREFIX.'.segment_id',
            'glue'      => 'and',
            'dynamic'   => null,
            'condition' => 'empty',
        ];

        self::assertSame($filterResult, $this->reportSubscriber->getCompanySegmentCondition($filter));
    }

    public function testGetCompanySegmentConditionWhenOperatorEqualsNotEmpty(): void
    {
        $filterResult         = 'filter result';
        $subQueryResult       = 'sub query';
        $this->queryBuilderMock->expects(self::once())
            ->method('getSQL')
            ->willReturn($subQueryResult);

        $this->exprMock
            ->expects(self::once())
            ->method('in')
            ->willReturnCallback(static function (string $field, string $value) use ($subQueryResult, $filterResult): string {
                self::assertSame('('.$subQueryResult.')', $value);

                return $filterResult;
            });

        $filter = [
            'column'    => ReportSubscriber::COMPANY_SEGMENTS_XREF_PREFIX.'.segment_id',
            'glue'      => 'and',
            'dynamic'   => null,
            'condition' => 'notEmpty',
        ];

        self::assertSame($filterResult, $this->reportSubscriber->getCompanySegmentCondition($filter));
    }
}
