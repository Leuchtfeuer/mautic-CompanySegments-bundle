<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Entity;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLogRepository;
use PHPUnit\Framework\TestCase;

class CompanyEventLogRepositoryTest extends TestCase
{
    private $registry;
    private $em;
    private $classMetadata;
    private $repository;

    protected function setUp(): void
    {
        $this->registry      = $this->createMock(ManagerRegistry::class);
        $this->em            = $this->createMock(EntityManagerInterface::class);
        $this->classMetadata = $this->createMock(ClassMetadata::class);

        $this->registry->method('getManagerForClass')->willReturn($this->em);

        $this->repository = $this->getMockBuilder(CompanyEventLogRepository::class)
            ->setConstructorArgs([$this->registry, $this->classMetadata])
            ->onlyMethods(['getTimelineResults', 'getEntityManager', 'getTableAlias', 'getEntities'])
            ->getMock();
    }

    public function testGetEntitiesExtractsSqlError()
    {
        $parentEntities = [
            [
                'properties' => [
                    'error' => 'SQLSTATE[23000]: Integrity constraint violation: Something went wrong',
                ],
            ],
            [
                'properties' => [
                    'error' => 'No error',
                ],
            ],
        ];

        // Provide a valid ClassMetadata mock
        $classMetadata = $this->createMock(ClassMetadata::class);

        // Create a stub for the parent repository to override getEntities
        $stub = $this->getMockBuilder(CompanyEventLogRepository::class)
            ->setConstructorArgs([$this->registry, $classMetadata])
            ->onlyMethods(['getEntities'])
            ->getMock();

        $stub->method('getEntities')->willReturn($parentEntities);

        $result = $stub->getEntities([]);
        $this->assertStringContainsString('Integrity constraint violation', $result[0]['properties']['error']);
    }

    public function testGetSpecificRowsReturnsEntities()
    {
        $entities = [
            ['foo' => 'bar'],
        ];

        $repo = $this->getMockBuilder(CompanyEventLogRepository::class)
            ->setConstructorArgs([$this->registry, $this->classMetadata])
            ->onlyMethods(['getEntities', 'getTableAlias'])
            ->getMock();

        $repo->method('getEntities')->willReturn($entities);
        $repo->method('getTableAlias')->willReturn('cel');

        $result = $repo->getSpecificRows(1, 'imported', [], 'company', 'import');
        $this->assertIsArray($result);
        $this->assertSame('bar', $result[0]['foo']);
    }

    public function testGetFailedRowsCallsGetSpecificRows()
    {
        $repo = $this->getMockBuilder(CompanyEventLogRepository::class)
            ->setConstructorArgs([$this->registry, $this->classMetadata])
            ->onlyMethods(['getSpecificRows'])
            ->getMock();

        $repo->expects($this->once())
            ->method('getSpecificRows')
            ->with('importId', 'failed', [], 'company', 'import')
            ->willReturn([['failed' => true]]);

        $result = $repo->getFailedRows('importId');
        $this->assertIsArray($result);
        $this->assertTrue($result[0]['failed']);
    }
}
