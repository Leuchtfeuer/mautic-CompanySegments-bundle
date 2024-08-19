<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Controller;

use Mautic\UserBundle\DataFixtures\ORM\LoadRoleData;
use Mautic\UserBundle\DataFixtures\ORM\LoadUserData;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\DataFixtures\ORM\LoadCompanySegmentData;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EnablePluginTrait;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;
use Symfony\Component\HttpFoundation\Request;

class CompanySegmentControllerTest extends MauticMysqlTestCase
{
    use EnablePluginTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enablePlugin(true);
    }

    public function testCreate(): void
    {
        $this->loadFixtures([LoadUserData::class, LoadRoleData::class], false);
        $segmentName = 'Segment test';
        $crawler     = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();

        self::assertCount(0, $crawler->filter('#companySegmentsTable > tbody'));

        $links = $crawler->filter('#toolbar a');
        self::assertCount(1, $links);
        $crawler = $this->client->clickLink('New');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('[name="company_segments"]')->form([
            'company_segments' => [
                'name' => $segmentName,
            ],
        ]);
        $crawler = $this->client->submit($form);
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('.page-header'));
        self::assertStringContainsString($segmentName, $crawler->filter('.page-header')->text());

        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#companySegmentsTable > tbody > tr'));
        self::assertStringContainsString($segmentName, $crawler->filter('#companySegmentsTable > tbody > tr')->eq(0)->filter('td')->eq(1)->text());
    }

    public function testCreateCancel(): void
    {
        $this->loadFixtures([LoadUserData::class, LoadRoleData::class], false);
        $crawler     = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();

        self::assertCount(0, $crawler->filter('#companyListTable > tbody'));

        $links = $crawler->filter('#toolbar a');
        self::assertCount(1, $links);
        $crawler = $this->client->clickLink('New');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('[name="company_segments"]')->form([
            'company_segments' => [],
        ]);
        $values = $form->getPhpValues();

        $values['company_segments']['buttons']['cancel'] = '';
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseIsSuccessful();

        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('#companyListTable > tbody > tr'));
    }

    public function testEdit(): void
    {
        $this->loadFixtures([LoadCompanySegmentData::class, LoadUserData::class, LoadRoleData::class], false);

        $segmentName = 'Segment test';
        $crawler     = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();

        $companySegment     = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        $companySegmentName = $companySegment->getName();
        self::assertNotNull($companySegmentName);
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($companySegmentName, $rows->eq(1)->filter('td')->eq(1)->text());
        $link = $rows->eq(1)->filter('td')->eq(1)->filter('a')->link();

        $crawler = $this->client->click($link);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($companySegmentName, $crawler->filter('.page-header')->text());

        $link = $crawler->filter('#toolbar a')->eq(0);
        self::assertSame('Edit', $link->text());
        $crawler = $this->client->click($link->link());
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('[name="company_segments"]')->form([
            'company_segments' => [
                'name' => $segmentName,
            ],
        ]);
        $crawler = $this->client->submit($form);
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('.page-header'));
        self::assertStringContainsString($segmentName, $crawler->filter('.page-header')->text());

        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($segmentName, $rows->eq(2)->filter('td')->eq(1)->text());
    }

    public function testDelete(): void
    {
        $this->loadFixtures([LoadCompanySegmentData::class, LoadUserData::class, LoadRoleData::class], false);

        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();

        $companySegment     = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        $companySegmentName = $companySegment->getName();
        self::assertNotNull($companySegmentName);
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($companySegmentName, $rows->eq(1)->filter('td')->eq(1)->text());
        $link = $rows->eq(1)->filter('td')->eq(1)->filter('a')->link();

        $crawler = $this->client->click($link);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($companySegmentName, $crawler->filter('.page-header')->text());

        $link = $crawler->filter('#toolbar a')->eq(3);
        self::assertSame('Delete', $link->text());
        $crawler = $this->client->click($link->link(Request::METHOD_POST));
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('#companySegmentsTable > tbody > tr'));
    }

    public function testBatchDelete(): void
    {
        $this->loadFixtures([LoadCompanySegmentData::class, LoadUserData::class, LoadRoleData::class], false);

        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();

        $companySegment     = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        $companySegmentName = $companySegment->getName();
        self::assertNotNull($companySegmentName);
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($companySegmentName, $rows->eq(1)->filter('td')->eq(1)->text());
        $link = $crawler->filter('.page-list-actions')->filter('a')->eq(0);
        self::assertSame('Delete Selected', $link->text());

        $crawler = $this->client->request(Request::METHOD_POST, $link->attr('href').'&ids='.json_encode([$companySegment->getId()], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('#companySegmentsTable > tbody > tr'));
    }

    public function testClone(): void
    {
        $this->loadFixtures([LoadCompanySegmentData::class, LoadUserData::class, LoadRoleData::class], false);

        $segmentName = 'Segment test';
        $crawler     = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();

        $companySegment     = $this->getCompanySegment(LoadCompanySegmentData::COMPANY_SEGMENT_FILTER_REVENUE);
        $companySegmentName = $companySegment->getName();
        self::assertNotNull($companySegmentName);
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString($companySegmentName, $rows->eq(1)->filter('td')->eq(1)->text());
        $link = $rows->eq(1)->filter('td')->eq(1)->filter('a')->link();

        $crawler = $this->client->click($link);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($companySegmentName, $crawler->filter('.page-header')->text());

        $link = $crawler->filter('#toolbar a')->eq(2);
        self::assertSame('Clone', $link->text());
        $crawler = $this->client->click($link->link());
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('[name="company_segments"]')->form([
            'company_segments' => [
                'name' => $segmentName,
            ],
        ]);
        $crawler = $this->client->submit($form);
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('.page-header'));
        self::assertStringContainsString($segmentName, $crawler->filter('.page-header')->text());

        $crawler = $this->client->request(Request::METHOD_GET, '/s/company-segments');
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#companySegmentsTable > tbody > tr');
        self::assertCount(4, $rows);
        self::assertStringContainsString($companySegmentName, $rows->eq(1)->filter('td')->eq(1)->text());
        self::assertStringContainsString($segmentName, $rows->eq(3)->filter('td')->eq(1)->text());
    }
}
