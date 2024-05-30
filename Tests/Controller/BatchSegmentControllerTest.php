<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Controller;

use Mautic\LeadBundle\DataFixtures\ORM\LoadCompanyData;
use Mautic\UserBundle\DataFixtures\ORM\LoadRoleData;
use Mautic\UserBundle\DataFixtures\ORM\LoadUserData;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\DataFixtures\ORM\LoadCompanySegmentData;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BatchSegmentControllerTest extends MauticMysqlTestCase
{
    public function testContactsAreAddedToThenRemovedFromCampaignsInBatch(): void
    {
        $this->loadFixtures([LoadCompanyData::class, LoadCompanySegmentData::class, LoadUserData::class, LoadRoleData::class], false);
        $segment   = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT);
        $segmentId = $segment->getId();
        $companyA  = $this->getCompany('company-1');
        $companyB  = $this->getCompany('company-2');
        $companyC  = $this->getCompany('company-3');

        $this->client->request(Request::METHOD_GET, '/s/company-segments/batch/company/view', [], [], $this->createAjaxHeaders());
        self::assertResponseIsSuccessful();
        $clientResponse = $this->client->getResponse();
        $content        = $clientResponse->getContent();
        self::assertNotFalse($content);
        $html = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($html);
        self::assertIsString($html['newContent']);
        $crawler = new Crawler($html['newContent'], 'http://localhost/s/company-segments/batch/company/view');
        $form    = $crawler->filter('form[name=company_batch]')->form([
            'company_batch[add]' => [$segmentId],
        ]);
        $payload = $form->getPhpValues();
        self::assertCount(1, $payload);
        self::assertArrayHasKey('company_batch', $payload);
        self::assertCount(3, $payload['company_batch']);
        self::assertArrayHasKey('_token', $payload['company_batch']);
        self::assertArrayHasKey('add', $payload['company_batch']);
        self::assertArrayHasKey('ids', $payload['company_batch']);

        $payload['company_batch']['ids'] = json_encode([$companyA->getId(), $companyB->getId(), $companyC->getId()], JSON_THROW_ON_ERROR);

        $this->client->request(Request::METHOD_POST, '/s/company-segments/batch/company/set', $payload);

        $clientResponse = $this->client->getResponse();
        self::assertEquals(Response::HTTP_OK, $clientResponse->getStatusCode());

        // re-fetch data
        $segment  = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT);
        $companyA = $this->getCompany('company-1');
        $companyB = $this->getCompany('company-2');
        $companyC = $this->getCompany('company-3');

        self::assertSame(
            [$companyA->getId() => $companyA, $companyB->getId() => $companyB, $companyC->getId() => $companyC],
            $segment->getCompanies()->toArray()
        );

        $content = $clientResponse->getContent();
        self::assertNotFalse($content);
        $response = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($response);
        self::assertTrue(isset($response['closeModal']), 'The response does not contain the `closeModal` param.');
        self::assertTrue($response['closeModal']);
        self::assertIsString($response['flashes']);
        self::assertStringContainsString('3 companies affected', $response['flashes']);

        $this->client->request(Request::METHOD_GET, '/s/company-segments/batch/company/view', [], [], $this->createAjaxHeaders());
        self::assertResponseIsSuccessful();
        $clientResponse = $this->client->getResponse();
        $content        = $clientResponse->getContent();
        self::assertNotFalse($content);
        $html = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($html);
        self::assertIsString($html['newContent']);
        $crawler = new Crawler($html['newContent'], 'http://localhost/s/company-segments/batch/company/view');
        $form    = $crawler->filter('form[name=company_batch]')->form([
            'company_batch[remove]' => [$segmentId],
        ]);
        $payload = $form->getPhpValues();
        self::assertCount(1, $payload);
        self::assertArrayHasKey('company_batch', $payload);
        self::assertCount(3, $payload['company_batch']);
        self::assertArrayHasKey('remove', $payload['company_batch']);
        self::assertArrayHasKey('_token', $payload['company_batch']);
        self::assertArrayHasKey('ids', $payload['company_batch']);

        $payload['company_batch']['ids'] = json_encode([$companyA->getId(), $companyB->getId(), $companyC->getId()], JSON_THROW_ON_ERROR);

        $this->client->request(Request::METHOD_POST, '/s/company-segments/batch/company/set', $payload);

        $clientResponse = $this->client->getResponse();
        self::assertEquals(Response::HTTP_OK, $clientResponse->getStatusCode());

        // re-fetch data
        $segment = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT);

        self::assertSame(
            [],
            $segment->getCompanies()->toArray()
        );

        $content = $clientResponse->getContent();
        self::assertNotFalse($content);
        $response = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($response);
        self::assertTrue(isset($response['closeModal']), 'The response does not contain the `closeModal` param.');
        self::assertTrue($response['closeModal']);
        self::assertIsString($response['flashes']);
        self::assertStringContainsString('3 companies affected', $response['flashes']);
    }
}
