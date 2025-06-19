<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLog;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanyTimelineEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CompanyEventLogModelTest extends TestCase
{
    private $em;
    private $security;
    private $dispatcher;
    private $router;
    private $translator;
    private $userHelper;
    private $logger;
    private $coreParametersHelper;

    protected function setUp(): void
    {
        $this->em                   = $this->createMock(EntityManagerInterface::class);
        $this->security             = $this->createMock(CorePermissions::class);
        $this->dispatcher           = $this->createMock(EventDispatcherInterface::class);
        $this->router               = $this->createMock(UrlGeneratorInterface::class);
        $this->translator           = $this->createMock(Translator::class);
        $this->userHelper           = $this->createMock(UserHelper::class);
        $this->logger               = $this->createMock(LoggerInterface::class);
        $this->coreParametersHelper = $this->createMock(CoreParametersHelper::class);
    }

    public function testGetRepositoryReturnsCompanyEventLogRepository()
    {
        $repo = $this->createMock(\Doctrine\Persistence\ObjectRepository::class);
        $this->em->method('getRepository')->with(CompanyEventLog::class)->willReturn($repo);

        $model = new CompanyEventLogModel(
            $this->em,
            $this->security,
            $this->dispatcher,
            $this->router,
            $this->translator,
            $this->userHelper,
            $this->logger,
            $this->coreParametersHelper
        );

        $this->assertSame($repo, $model->getRepository());
    }

    public function testGetEngagementsReturnsExpectedPayload()
    {
        $company     = $this->createMock(Company::class);
        $filters     = ['type' => 'test'];
        $orderBy     = ['date' => 'DESC'];
        $page        = 1;
        $limit       = 10;
        $forTimeline = true;

        $eventMock = $this->createMock(CompanyTimelineEvent::class);
        $eventMock->method('getEvents')->willReturn([['id' => 1]]);
        $eventMock->method('getEventTypes')->willReturn(['type1']);
        $eventMock->method('getEventCounter')->willReturn(['total' => 1]);
        $eventMock->method('getMaxPage')->willReturn(1);

        $this->coreParametersHelper->method('get')->with('site_url')->willReturn('http://localhost');
        $this->dispatcher->method('dispatch')->willReturn($eventMock);

        $model = new CompanyEventLogModel(
            $this->em,
            $this->security,
            $this->dispatcher,
            $this->router,
            $this->translator,
            $this->userHelper,
            $this->logger,
            $this->coreParametersHelper
        );

        $result = $model->getEngagements($company, $filters, $orderBy, $page, $limit, $forTimeline);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('events', $result);
        $this->assertSame([['id' => 1]], $result['events']);
        $this->assertSame($filters, $result['filters']);
        $this->assertSame($orderBy, $result['order']);
        $this->assertSame(['type1'], $result['types']);
        $this->assertSame(1, $result['total']);
        $this->assertSame($page, $result['page']);
        $this->assertSame($limit, $result['limit']);
        $this->assertSame(1, $result['maxPages']);
    }
}
