<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Entity;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLog;
use PHPUnit\Framework\TestCase;

class CompanyEventLogTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $eventLog = new CompanyEventLog();

        $this->assertInstanceOf(\DateTime::class, $eventLog->getDateAdded());
        $this->assertNull($eventLog->getCompany());
        $this->assertNull($eventLog->getUserId());
        $this->assertNull($eventLog->getUserName());
        $this->assertNull($eventLog->getBundle());
        $this->assertNull($eventLog->getObject());
        $this->assertNull($eventLog->getObjectId());
        $this->assertNull($eventLog->getAction());
        $this->assertNull($eventLog->getProperties());
    }

    public function testSettersAndGetters(): void
    {
        $eventLog = new CompanyEventLog();
        $company  = $this->createMock(Company::class);

        $eventLog->setCompany($company);
        $eventLog->setUserId(42);
        $eventLog->setUserName('John Doe');
        $eventLog->setBundle('TestBundle');
        $eventLog->setObject('TestObject');
        $eventLog->setObjectId(123);
        $eventLog->setAction('create');
        $date = new \DateTime('2020-01-01');
        $eventLog->setDateAdded($date);
        $eventLog->setProperties(['foo' => 'bar']);

        $this->assertSame($company, $eventLog->getCompany());
        $this->assertSame(42, $eventLog->getUserId());
        $this->assertSame('John Doe', $eventLog->getUserName());
        $this->assertSame('TestBundle', $eventLog->getBundle());
        $this->assertSame('TestObject', $eventLog->getObject());
        $this->assertSame(123, $eventLog->getObjectId());
        $this->assertSame('create', $eventLog->getAction());
        $this->assertSame($date, $eventLog->getDateAdded());
        $this->assertSame(['foo' => 'bar'], $eventLog->getProperties());
    }

    public function testAddProperty(): void
    {
        $eventLog = new CompanyEventLog();
        $eventLog->setProperties(['foo' => 'bar']);
        $eventLog->addProperty('baz', 'qux');

        $this->assertSame(['foo' => 'bar', 'baz' => 'qux'], $eventLog->getProperties());
    }
}
