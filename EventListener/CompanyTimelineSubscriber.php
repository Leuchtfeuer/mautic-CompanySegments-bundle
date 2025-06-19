<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\CoreBundle\Translation\Translator;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLogRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanyTimelineEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\LeuchfeuerCompanySegmentsEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;

class CompanyTimelineSubscriber implements EventSubscriberInterface
{
    use TimelineCompanyEventLogTrait;

    public function __construct(
        private CompanyEventLogModel $companyEventLogModel,
        private Translator $translator,
        private CompanySegmentModel $companySegmentModel,
        private RouterInterface $router,
        private CompanyEventLogRepository $companyEventLogRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeuchfeuerCompanySegmentsEvents::TIMELINE_ON_GENERATE => ['onTimelineGenerate', 0],
        ];
    }

    /**
     * Compile events for the lead timeline.
     */
    public function onTimelineGenerate(CompanyTimelineEvent $event): void
    {
        $eventTypes = [
            'company.segmentadd'    => 'mautic.company_segments.timeline.segment.add',
            'company.segmentremove' => 'mautic.company_segments.timeline.segment.remove',
        ];

        $event->getEventFilters();

        foreach ($eventTypes as $type => $label) {
            $name = $this->translator->trans($label);
            $event->addEventType($type, $name);

            if (!$event->isApplicable($type)) {
                continue;
            }

            switch ($type) {
                case 'company.segmentadd':
                    $this->timelineSegmentAdd($event, $type);
                    break;

                case 'company.segmentremove':
                    $this->timelineSegmentRemove($event, $type);
                    break;
            }
        }
    }

    private function timelineSegmentAdd(CompanyTimelineEvent $event, string $eventType): void
    {
        $this->addCompanySegmentEvents(
            $event,
            $eventType,
            'mautic.company_segments.timeline.segment.add',
            'ri-add-box-fill',
            'company',
            'company_segment',
            'added',
        );
    }

    private function timelineSegmentRemove(CompanyTimelineEvent $event, string $eventType): void
    {
        $this->addCompanySegmentEvents(
            $event,
            $eventType,
            'mautic.company_segments.timeline.segment.remove',
            'ri-delete-bin-2-fill',
            'company',
            'company_segment',
            'removed',
        );
    }
}
