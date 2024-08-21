<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Segment\Query\Filter;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Test\Doctrine\MockedConnectionTrait;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Provider\FilterOperatorProviderInterface;
use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\ContactSegmentFilterCrate;
use Mautic\LeadBundle\Segment\ContactSegmentFilterFactory;
use Mautic\LeadBundle\Segment\ContactSegmentFilterOperator;
use Mautic\LeadBundle\Segment\Decorator\BaseDecorator;
use Mautic\LeadBundle\Segment\Query\Filter\FilterQueryBuilderInterface;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use Mautic\LeadBundle\Segment\RandomParameterName;
use Mautic\LeadBundle\Segment\TableSchemaColumnsCache;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Exception\SegmentNotFoundException;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Segment\Query\CompanySegmentQueryBuilder;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Segment\Query\Filter\SegmentReferenceFilterQueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SegmentReferenceFilterQueryBuilderTest extends MauticMysqlTestCase
{
    use MockedConnectionTrait;

    /**
     * @var MockObject&CompanyRepository
     */
    private MockObject $companyRepositoryMock;

    /**
     * @var MockObject&CompanySegmentRepository
     */
    private MockObject $companySegmentRepositoryMock;

    /**
     * @var MockObject&RandomParameterName
     */
    private MockObject $randomParameterMock;

    /**
     * @var MockObject&EventDispatcherInterface
     */
    private MockObject $dispatcherMock;

    /**
     * @var Connection&MockObject
     */
    private MockObject $connectionMock;

    private SegmentReferenceFilterQueryBuilder $queryBuilder;

    private CompanySegment $segment;

    public function setUp(): void
    {
        parent::setUp();
        $this->companyRepositoryMock        = $this->createMock(CompanyRepository::class);
        $this->companySegmentRepositoryMock = $this->createMock(CompanySegmentRepository::class);
        $this->randomParameterMock          = $this->createMock(RandomParameterName::class);
        $this->dispatcherMock               = $this->createMock(EventDispatcherInterface::class);
        $mockedConnection                   = $this->getMockedConnection();
        self::assertInstanceOf(Connection::class, $mockedConnection);
        self::assertInstanceOf(MockObject::class, $mockedConnection);
        $this->connectionMock = $mockedConnection;

        $this->queryBuilder = new SegmentReferenceFilterQueryBuilder(
            $this->randomParameterMock,
            new CompanySegmentQueryBuilder($this->em, $this->companyRepositoryMock, $this->companySegmentRepositoryMock, $this->randomParameterMock, $this->dispatcherMock),
            $this->em,
            $this->createMock(ContactSegmentFilterFactory::class), // probably
            $this->dispatcherMock
        );

        $this->segment = $this->createNewSegment();
    }

    public function testGetServiceId(): void
    {
        self::assertEquals(
            SegmentReferenceFilterQueryBuilder::class,
            $this->queryBuilder::getServiceId()
        );
    }

    /**
     * @return array<mixed>
     */
    public function dataApplyQuery(): iterable
    {
        yield 'eq' => ['eq', 'SELECT 1 FROM <prefix>companies comp WHERE EXISTS(SELECT null FROM <prefix>companies queryAlias WHERE (comp.id = queryAlias.id) AND ((EXISTS(SELECT null FROM <prefix>companies_segments para1 WHERE (queryAlias.id = para1.company_id) AND ((para1.segment_id = %1$s) AND ((para1.manually_added = 1) OR (para1.manually_removed = 0))))) AND (EXISTS(SELECT null FROM <prefix>companies_segments para2 WHERE (queryAlias.id = para2.company_id) AND (para2.segment_id = %1$s)))))'];
        yield 'notExists' => ['notExists', 'SELECT 1 FROM <prefix>companies comp WHERE NOT EXISTS(SELECT null FROM <prefix>companies queryAlias WHERE (comp.id = queryAlias.id) AND ((EXISTS(SELECT null FROM <prefix>companies_segments para1 WHERE (queryAlias.id = para1.company_id) AND ((para1.segment_id = %1$s) AND ((para1.manually_added = 1) OR (para1.manually_removed = 0))))) AND (EXISTS(SELECT null FROM <prefix>companies_segments para2 WHERE (queryAlias.id = para2.company_id) AND (para2.segment_id = %1$s)))))'];
    }

    /**
     * @dataProvider dataApplyQuery
     */
    public function testApplyQuery(string $operator, string $expectedQuery): void
    {
        $this->companyRepositoryMock->method('getTableAlias')
            ->willReturn('comp');
        $queryBuilder = new QueryBuilder($this->connectionMock);
        $queryBuilder->select('1');
        $queryBuilder->from(MAUTIC_TABLE_PREFIX.'companies', 'comp');

        $filter = $this->getContactSegmentFilter($operator, (string) $this->segment->getId());

        $this->randomParameterMock->method('generateRandomParameterName')
            ->willReturnOnConsecutiveCalls('queryAlias', 'para1', 'para2');

        $this->queryBuilder->applyQuery($queryBuilder, $filter);

        $expectedQuery = str_replace('<prefix>', MAUTIC_TABLE_PREFIX, $expectedQuery);

        // Address https://github.com/mautic/mautic/commit/cf7c599e9aa684db7f0c5d9613980608838775b5
        if (version_compare(constant('MAUTIC_VERSION'), '5.1', 'lt')) {
            $expectedQuery = str_replace([
                'manually_added = 1',
                'manually_removed = 0',
            ], [
                'manually_added = \'1\'',
                'manually_removed = \'\'',
            ], $expectedQuery);
        }

        self::assertSame(sprintf($expectedQuery, $this->segment->getId()), $queryBuilder->getDebugOutput());
    }

    public function testApplyQueryWhenSegmentNotExist(): void
    {
        $this->companyRepositoryMock->method('getTableAlias')
            ->willReturn('comp');
        $queryBuilder = new QueryBuilder($this->connectionMock);
        $queryBuilder->select('1');
        $queryBuilder->from(MAUTIC_TABLE_PREFIX.'companies', 'comp');

        $filter = $this->getContactSegmentFilter('eq', 'non_exist_segment_id');

        $this->randomParameterMock->method('generateRandomParameterName')
            ->willReturnOnConsecutiveCalls('queryAlias', 'para1', 'para2');

        $this->expectException(SegmentNotFoundException::class);
        $this->queryBuilder->applyQuery($queryBuilder, $filter);
    }

    private function createNewSegment(): CompanySegment
    {
        $segment = new CompanySegment();
        $segment->setName('Test Segment');
        $segment->setAlias('test_segment');
        $segment->isPublished(true);
        $segment->setPublicName('Test Segment');

        $this->em->persist($segment);
        $this->em->flush();

        return $segment;
    }

    private function getContactSegmentFilter(string $operator, string $parameterValue): ContactSegmentFilter
    {
        return new ContactSegmentFilter(
            new ContactSegmentFilterCrate(
                [
                    'object'     => 'company',
                    'glue'       => 'and',
                    'field'      => CompanySegmentModel::PROPERTIES_FIELD,
                    'type'       => CompanySegmentModel::PROPERTIES_FIELD,
                    'operator'   => $operator,
                    'properties' => [
                        'filter' => [
                            0 => $parameterValue,
                        ],
                    ],
                    'filter' => [
                        0 => $parameterValue,
                    ],
                    'display' => null,
                ]
            ),
            new BaseDecorator(new ContactSegmentFilterOperator(
                $this->createMock(FilterOperatorProviderInterface::class)
            )),
            new TableSchemaColumnsCache($this->createMock(EntityManager::class)),
            $this->createMock(FilterQueryBuilderInterface::class)
        );
    }
}
