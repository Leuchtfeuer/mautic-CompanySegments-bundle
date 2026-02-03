<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Import;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EnablePluginTrait;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\HelperCompanySegmentTestTrait;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

final class ImportCompanySegmentSubscriberTest extends MauticMysqlTestCase
{
    use EnablePluginTrait;
    use HelperCompanySegmentTestTrait;

    protected $useCleanupRollback = false;

    private string $csvFile;
    private ?CompanySegment $segment1 = null;
    private ?CompanySegment $segment2 = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enablePlugin(true);
        $this->segment1 = $this->createCompanySegment('Test Segment 1', 'test-segment-1');
        $this->segment2 = $this->createCompanySegment('Test Segment 2', 'test-segment-2');
    }

    protected function beforeTearDown(): void
    {
        if (isset($this->csvFile) && file_exists($this->csvFile)) {
            unlink($this->csvFile);
        }
    }

    public function testBrowserImportAssignsCompaniesToSegments(): void
    {
        $this->loginUser('admin');
        $this->generateCompanyCsv([
            ['companyname', 'companyemail', 'companycity'],
            ['Acme Corp', 'contact@acme.com', 'New York'],
            ['Tech Solutions', 'info@techsolutions.com', 'San Francisco'],
            ['Global Industries', 'hello@global.com', 'London'],
        ]);

        // Step 1: Upload CSV
        $crawler      = $this->client->request(Request::METHOD_GET, '/s/companies/import/new');
        $uploadButton = $crawler->selectButton('Upload');
        $form         = $uploadButton->form();
        $form->setValues([
            'lead_import[file]'       => $this->csvFile,
            'lead_import[batchlimit]' => 100,
            'lead_import[delimiter]'  => ',',
            'lead_import[enclosure]'  => '"',
            'lead_import[escape]'     => '\\',
        ]);
        $html = $this->client->submit($form);

        Assert::assertStringContainsString(
            'Match the columns',
            $html->text(null, false),
            'Should reach field mapping page after upload'
        );

        $browserImportButton = $html->selectButton('Import in browser');
        $importForm          = $browserImportButton->form();

        Assert::assertNotNull($this->segment1);
        Assert::assertNotNull($this->segment2);

        $importForm->setValues([
            'lead_field_import[company_segments]' => [
                $this->segment1->getId(),
                $this->segment2->getId(),
            ],
        ]);

        $this->client->submit($importForm);

        for ($i = 0; $i < 10; ++$i) {
            $crawler = $this->client->request(Request::METHOD_GET, '/s/companies/import/new');
            $content = $crawler->html();

            if (str_contains($content, 'panel-success') && str_contains($content, 'Success!')) {
                break;
            }

            usleep(100000);
        }

        $this->em->clear();
        $companies = $this->em->getRepository(Company::class)->findBy([
            'name' => ['Acme Corp', 'Tech Solutions', 'Global Industries'],
        ]);
        Assert::assertCount(3, $companies, 'Expected 3 companies to be imported');

        Assert::assertNotNull($this->segment1);
        Assert::assertNotNull($this->segment2);
        $segment1 = $this->segment1;
        $segment2 = $this->segment2;

        foreach ($companies as $company) {
            $this->assertCompanyInSegments($company, [$segment1, $segment2]);
        }

        $this->em->clear();
        $import = $this->em->getRepository(Import::class)->findOneBy(['object' => 'company']);
        Assert::assertNotNull($import, 'Import entity should exist');
        Assert::assertEquals(Import::IMPORTED, $import->getStatus(), 'Import should be marked as imported');
    }

    public function testBackgroundImportAssignsCompaniesToSegments(): void
    {
        $this->loginUser('admin');
        $this->generateCompanyCsv([
            ['companyname', 'companyemail', 'companycity'],
            ['Background Co', 'contact@background.com', 'Chicago'],
            ['Command Inc', 'info@command.com', 'Boston'],
        ]);

        $crawler      = $this->client->request(Request::METHOD_GET, '/s/companies/import/new');
        $uploadButton = $crawler->selectButton('Upload');
        $form         = $uploadButton->form();
        $form->setValues([
            'lead_import[file]'       => $this->csvFile,
            'lead_import[batchlimit]' => 100,
            'lead_import[delimiter]'  => ',',
            'lead_import[enclosure]'  => '"',
            'lead_import[escape]'     => '\\',
        ]);
        $html = $this->client->submit($form);

        Assert::assertStringContainsString(
            'Match the columns',
            $html->text(null, false),
            'Should reach field mapping page after upload'
        );

        $backgroundImportButton = $html->selectButton('Import in background');
        $importForm             = $backgroundImportButton->form();

        Assert::assertNotNull($this->segment1);

        $importForm->setValues([
            'lead_field_import[company_segments]' => [$this->segment1->getId()],
        ]);

        $this->client->submit($importForm);

        $this->em->clear();
        $import = $this->em->getRepository(Import::class)->findOneBy(['object' => 'company']);
        Assert::assertNotNull($import, 'Import should be queued');
        Assert::assertEquals(Import::QUEUED, $import->getStatus(), 'Import should be queued');

        $properties = $import->getProperties();
        Assert::assertArrayHasKey('company_segments', $properties);
        Assert::assertNotNull($this->segment1);
        $segmentIds = $properties['company_segments'];
        Assert::assertIsArray($segmentIds);
        Assert::assertContains($this->segment1->getId(), $segmentIds);

        $commandTester = $this->testSymfonyCommand('mautic:import', [
            '-i'      => $import->getId(),
            '--limit' => 1000,
        ]);

        $output = $commandTester->getDisplay();
        Assert::assertStringContainsString('2 items created', $output, 'Should create 2 companies');

        $this->em->clear();
        $companies = $this->em->getRepository(Company::class)->findBy([
            'name' => ['Background Co', 'Command Inc'],
        ]);
        Assert::assertCount(2, $companies, 'Expected 2 companies to be imported');

        Assert::assertNotNull($this->segment1);
        $segment1 = $this->segment1;

        foreach ($companies as $company) {
            $this->assertCompanyInSegments($company, [$segment1]);
        }

        $this->em->clear();
        $import = $this->em->getRepository(Import::class)->find($import->getId());
        Assert::assertNotNull($import);
        Assert::assertEquals(Import::IMPORTED, $import->getStatus());
    }

    /**
     * @param array<array<string>> $rows
     */
    private function generateCompanyCsv(array $rows): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'mautic_company_import_test_').'.csv';
        $file    = fopen($tmpFile, 'wb');
        Assert::assertNotFalse($file, 'Failed to create temporary CSV file');

        foreach ($rows as $line) {
            fputcsv($file, $line);
        }

        fclose($file);
        $this->csvFile = $tmpFile;
    }

    /**
     * @param CompanySegment[] $expectedSegments
     */
    private function assertCompanyInSegments(Company $company, array $expectedSegments): void
    {
        $assignments = $this->em->getRepository(CompaniesSegments::class)->findBy([
            'company' => $company,
        ]);

        Assert::assertCount(
            count($expectedSegments),
            $assignments,
            sprintf('Company "%s" should be assigned to %d segment(s)', $company->getName(), count($expectedSegments))
        );

        $assignedSegmentIds = array_map(
            fn (CompaniesSegments $cs) => $cs->getCompanySegment()->getId(),
            $assignments
        );

        foreach ($expectedSegments as $expectedSegment) {
            Assert::assertContains(
                $expectedSegment->getId(),
                $assignedSegmentIds,
                sprintf(
                    'Company "%s" should be assigned to segment "%s"',
                    $company->getName(),
                    $expectedSegment->getName()
                )
            );
        }
    }
}
