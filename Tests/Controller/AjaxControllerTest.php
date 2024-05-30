<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Controller;

use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;

class AjaxControllerTest extends MauticMysqlTestCase
{
    public function testCompanySegmentFilter(): void
    {
        $this->client->xmlHttpRequest('POST', '/s/ajax', [
            'action'      => 'plugin:LeuchtfeuerCompanySegments:loadCompanySegmentFilterForm',
            'fieldAlias'  => 'date_modified',
            'fieldObject' => 'company_segments',
            'operator'    => OperatorOptions::EQUAL_TO,
            'filterNum'   => '1',
        ], [], $this->createAjaxHeaders());

        $response = $this->client->getResponse();
        self::assertEquals(200, $response->getStatusCode());
        $data = $response->getContent();
        self::assertNotFalse($data);
        $json = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($json);
        self::assertArrayHasKey('viewParameters', $json);
        self::assertArrayHasKey('form', $json['viewParameters']);
        self::assertIsString($json['viewParameters']['form']);
        self::assertStringContainsString('company_segments_filters_1_properties_filter', $json['viewParameters']['form']);
        self::assertStringContainsString('name="company_segments[filters][1][properties][filter]"', $json['viewParameters']['form']);
    }
}
