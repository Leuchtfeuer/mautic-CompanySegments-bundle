<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Event\LeadTimelineEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLog;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanyTimelineEvent;
use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\LeuchfeuerCompanySegmentsEvents;

class CompanyEventLogModel
{

    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private CoreParametersHelper $coreParametersHelper,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<mixed, mixed>|null $filters
     */
    public function getEngagements(?Company $company = null, ?array $filters = null, ?array $orderBy = null, int $page = 1, int $limit = 25, bool $forTimeline = true): array
    {
        $event = $this->dispatcher->dispatch(
            new CompanyTimelineEvent($company, $filters, $orderBy, $page, $limit, $forTimeline, $this->coreParametersHelper->get('site_url')),
            LeuchfeuerCompanySegmentsEvents::TIMELINE_ON_GENERATE
        );

        $payload = [
            'events'   => $event->getEvents(),
            'filters'  => $filters,
            'order'    => $orderBy,
            'types'    => $event->getEventTypes(),
            'total'    => $event->getEventCounter()['total'],
            'page'     => $page,
            'limit'    => $limit,
            'maxPages' => $event->getMaxPage(),
        ];

        return ($forTimeline) ? $payload : [$payload, $event->getSerializerGroups()];
    }

    public function getRepository()
    {
        return $this->em->getRepository(CompanyEventLog::class);
    }

    /**
     * @return array
     */
    public function getEngagementTypes()
    {
        $event = new CompanyTimelineEvent();
        $event->fetchTypesOnly();

        $this->dispatcher->dispatch($event, LeuchfeuerCompanySegmentsEvents::TIMELINE_ON_GENERATE);

        return $event->getEventTypes();
    }

    /**
     * Get engagement counts by time unit.
     *
     * @param string $unit
     */
    public function getEngagementCount(Company $company, ?\DateTime $dateFrom = null, ?\DateTime $dateTo = null, $unit = 'm', ?ChartQuery $chartQuery = null): array
    {
        $event = new CompanyTimelineEvent($company);
        $event->setCountOnly($dateFrom, $dateTo, $unit, $chartQuery);

        $this->dispatcher->dispatch($event, LeuchfeuerCompanySegmentsEvents::TIMELINE_ON_GENERATE);

        return $event->getEventCounter();
    }
}