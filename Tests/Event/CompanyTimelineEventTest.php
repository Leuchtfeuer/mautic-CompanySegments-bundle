<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Event;

use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanyTimelineEvent;
use PHPUnit\Framework\TestCase;

class CompanyTimelineEventTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $company    = $this->createMock(\Mautic\LeadBundle\Entity\Company::class);
        $filters    = ['action' => 'created'];
        $properties = ['foo' => 'bar'];

        $event = new CompanyTimelineEvent($company, $filters, $properties);

        $this->assertSame($company, $event->getCompany());
    }
}
