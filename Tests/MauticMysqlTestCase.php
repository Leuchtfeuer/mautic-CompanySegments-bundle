<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests;

use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\Persistence\ManagerRegistry;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use Symfony\Component\BrowserKit\Exception\BadMethodCallException;

class MauticMysqlTestCase extends \Mautic\CoreBundle\Test\MauticMysqlTestCase
{
    protected ?string $tmpDir = null;
    protected ?string $testHost;
    protected ?ReferenceRepository $fixtures = null;

    protected function setUp(): void
    {
        unset($this->configParams['site_url']);

        parent::setUp();
    }

    protected function beforeTearDown(): void
    {
        parent::beforeTearDown();

        if (static::$booted) {
            $parameter = static::$kernel->getContainer()->getParameter('mautic.contact_export_dir');
            \assert(is_string($parameter));
            $this->tmpDir   = rtrim($parameter, '/').'/';
            $parameter      = static::$kernel->getContainer()->getParameter('mautic.site_url');
            \assert(is_string($parameter));
            $this->testHost = rtrim($parameter, '/').'/';
            $parameter      = static::$kernel->getContainer()->getParameter('mautic.application_dir');
            \assert(is_string($parameter));
            $this->testHost .= trim(str_replace($parameter, '', $this->tmpDir), '/').'/';
        }
    }

    public function onNotSuccessfulTest(\Throwable $t): void
    {
        try {
            $client = $this->client;
        } catch (\Error $e) {
            throw $t;
        }

        if ($client->getHistory()->isEmpty()) {
            throw $t;
        }

        $response = null;

        try {
            if (null !== $this->tmpDir) {
                $response = $client->getResponse();
            }
        } catch (BadMethodCallException $badMethodCallException) {
            throw $t;
        }

        if (null !== $response) {
            if (!is_dir($this->tmpDir)) {
                mkdir($this->tmpDir);
            }

            $temporaryOutput    = tempnam($this->tmpDir, 'test').'.html';
            $temporaryOutputWeb = $this->testHost.basename($temporaryOutput);
            file_put_contents($temporaryOutput, $response->getContent());
            chmod($temporaryOutput, 0644);

            $reflectionObject   = new \ReflectionObject($t);
            $reflectionProperty = $reflectionObject->getProperty('message');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($t, $t->getMessage()."\nOut: ".$temporaryOutputWeb);
        }

        $this->fixtures = null;

        self::ensureKernelShutdown();

        parent::onNotSuccessfulTest($t);
    }

    /**
     * @param array<mixed> $classNames
     */
    protected function loadFixtures(array $classNames = [], bool $append = true): ?AbstractExecutor
    {
        $executor = parent::loadFixtures($classNames, $append);
        \assert(null !== $executor);
        $this->fixtures = $executor->getReferenceRepository();

        return $executor;
    }

    public function getDoctrine(): ManagerRegistry
    {
        $doctrine = self::$kernel->getContainer()->get('doctrine');

        if (!$doctrine instanceof ManagerRegistry) {
            throw new \RuntimeException('Doctrine is not doctrine?');
        }

        return $doctrine;
    }

    public function getCompanySegment(string $reference): CompanySegment
    {
        if (null === $this->fixtures) {
            throw new \RuntimeException('Fixtures are not loaded.');
        }

        $return = $this->fixtures->getReference($reference, CompanySegment::class);

        if (!$this->getDoctrine()->getManager()->contains($return)) {
            $return = $this->getDoctrine()->getRepository(CompanySegment::class)->find($return->getId());
        }

        if (null === $return) {
            throw new \RuntimeException('Can not refresh or find the entity.');
        }

        $this->getDoctrine()->getManager()->refresh($return);

        return $return;
    }

    public function getCompany(string $reference): Company
    {
        if (null === $this->fixtures) {
            throw new \RuntimeException('Fixtures are not loaded.');
        }

        $return = $this->fixtures->getReference($reference, Company::class);

        if (!$this->getDoctrine()->getManager()->contains($return)) {
            $return = $this->getDoctrine()->getRepository(Company::class)->find($return->getId());
        }

        if (null === $return) {
            throw new \RuntimeException('Can not refresh or find the entity.');
        }

        $this->getDoctrine()->getManager()->refresh($return);

        return $return;
    }
}
