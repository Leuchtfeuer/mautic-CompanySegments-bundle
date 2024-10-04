<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Functional\Controller\Api;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EnablePluginTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanySegmentApiControllerTest extends MauticMysqlTestCase
{
    use EnablePluginTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enablePlugin(true);
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    public function testGetCompanySegments(): void
    {
        $this->addCompanySegment('Segment test', 'segment-test', true);
        $this->addCompanySegment('Segment test 2', 'segment-test-2', true);
        $this->client->request(Request::METHOD_GET, '/api/companysegments');
        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['companysegments']);
    }

    public function testGetCompanySegment(): void
    {
        $companySegment = $this->addCompanySegment('Segment test', 'segment-test', true);
        $this->client->request(Request::METHOD_GET, '/api/companysegments/'.$companySegment->getId());
        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Segment test', $data['companysegment']['name']);
    }

    public function testAddCompanySegment(): void
    {
        $data = [
            'name'        => 'Segment test',
            'alias'       => 'segment-test-a',
            'isPublished' => '1',
        ];
        $this->client->request(Request::METHOD_POST, '/api/companysegments/new', $data);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Segment test', $data['companysegment']['name']);
        $companySegment = $this->em->getRepository(CompanySegment::class)->find($data['companysegment']['id']);
        $this->assertSame('Segment test', $companySegment->getName());
    }

    public function testEditCompanySegment(): void
    {
        $companySegment = $this->addCompanySegment('Segment test', 'segment-test', true);
        $data           = [
            'name'        => 'Segment test edited',
            'alias'       => 'segment-test-a',
            'isPublished' => '1',
        ];
        $this->client->request(Request::METHOD_PATCH, '/api/companysegments/'.$companySegment->getId().'/edit', $data);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Segment test edited', $data['companysegment']['name']);
        $companySegment = $this->em->getRepository(CompanySegment::class)->find($data['companysegment']['id']);
        $this->assertSame('Segment test edited', $companySegment->getName());
    }

    public function testDeleteCompanySegment(): void
    {
        $companySegment = $this->addCompanySegment('Segment test', 'segment-test', true);
        $tempId         = $companySegment->getId();
        $this->client->request(Request::METHOD_DELETE, '/api/companysegments/'.$companySegment->getId().'/delete');
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $companySegment = $this->em->getRepository(CompanySegment::class)->find($tempId);
        $this->assertNull($companySegment);
    }

    public function testBatchAddCompaniesSegments(): void
    {
        $data = [
            [
                'name'        => 'Segment test edited',
                'alias'       => 'segment-test-a',
                'isPublished' => '1',
            ],
            [
                'name'        => 'Segment test 2 edited',
                'alias'       => 'segment-test-2-a',
                'isPublished' => '1',
            ],
        ];
        $this->client->request(Request::METHOD_POST, '/api/companysegments/batch/new', $data);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $data['companysegments']);
        $this->assertSame(Response::HTTP_CREATED, $data['statusCodes'][0]);
        $this->assertSame(Response::HTTP_CREATED, $data['statusCodes'][1]);
    }

    public function testAddBatchCompanySegmentOneSuccessAndOneFail(): void
    {
        $data = [
            [
                'name'        => 'Segment test edited',
                'alias'       => 'segment-test-a',
                'isPublished' => '1',
            ],
            [
                'alias'       => 'segment-test-2-a',
                'isPublished' => '1',
            ],
        ];
        $this->client->request(Request::METHOD_POST, '/api/companysegments/batch/new', $data);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data['companysegments']);
        $this->assertSame(Response::HTTP_CREATED, $data['statusCodes'][0]);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $data['statusCodes'][1]);
        $this->assertCount(1, $data['errors']);
    }

    public function testEditBatchCompanySegmentOneSuccessAndTwoFail(): void
    {
        $companySegment1 = $this->addCompanySegment('Segment test', 'segment-test', true);
        $data            = [
            [
                'id'          => $companySegment1->getId(),
                'name'        => 'Segment test edited',
                'alias'       => 'segment-test-a',
                'isPublished' => '1',
            ],
            [
                'alias'       => 'segment-test-2-a',
                'isPublished' => '1',
            ],
            [
                'id'          => 999,
                'name'        => 'Segment test 3',
                'alias'       => 'segment-test-3',
                'isPublished' => '1',
            ],
        ];
        $this->client->request(Request::METHOD_PATCH, '/api/companysegments/batch/edit', $data);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data['companysegments']);
        $this->assertSame(Response::HTTP_OK, $data['statusCodes'][0]);
        $this->assertSame(Response::HTTP_NOT_FOUND, $data['statusCodes'][1]);
        $this->assertSame(Response::HTTP_NOT_FOUND, $data['statusCodes'][2]);
        $this->assertCount(2, $data['errors']);
    }

    private function addCompanySegment(string $name, string $alias, bool $isPublished = true): CompanySegment
    {
        $companySegment = new CompanySegment();
        $companySegment->setName($name);
        $companySegment->setAlias($alias);
        $companySegment->setIsPublished($isPublished);
        $this->em->persist($companySegment);
        $this->em->flush();

        return $companySegment;
    }
}