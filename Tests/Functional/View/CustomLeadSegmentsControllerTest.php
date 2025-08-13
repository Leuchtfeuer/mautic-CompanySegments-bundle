<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Functional\View;

use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;

class CustomLeadSegmentsControllerTest extends MauticMysqlTestCase
{
    public function testViewLeadSegments(): void
    {
        $crawler  = $this->client->request('GET', '/s/segments/new');
        $response = $this->client->getResponse();
        self::assertNotFalse($response->getContent());
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Address Line 1', $response->getContent());
        self::assertStringContainsString('Company Segment Membership', $response->getContent());
    }
}
