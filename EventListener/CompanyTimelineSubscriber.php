<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLog;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\LeuchtfeuerCompanySegmentsBundle;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\LeuchfeuerCompanySegmentsEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanyTimelineEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CompanyTimelineSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CompanyEventLogModel $companyEventLogModel,
        private TranslatorInterface $translator,
        private CompanySegmentModel $companySegmentModel,
        private RouterInterface $router,
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
            'company.segmentadd' => 'mautic.company_segments.timeline.segment.add',
            'company.segmentremove' => 'mautic.company_segments.timeline.segment.remove',
        ];

        // Following events takes the event from the lead itself, so not applicable for API
        // where we are getting events for all leads.
//        if ($event->isForTimeline()) {
//            $eventTypes['lead.create']     = 'mautic.lead.event.create';
//            $eventTypes['lead.identified'] = 'mautic.lead.event.identified';
//            $eventTypes['lead.ipadded']    = 'mautic.lead.event.ipadded';
//            $eventTypes['lead.apiadded']   = 'mautic.lead.event.apiadded';
//        }

        $filters = $event->getEventFilters();

        // Temporary measure as the other event types don't have tests yet
//        if ($this->isTest) {
//            $eventTypes = [
//                'lead.apiadded' => 'mautic.lead.event.apiadded',
//            ];
//        }

        foreach ($eventTypes as $type => $label) {
            $name = $this->translator->trans($label);
            $event->addEventType($type, $name);

            if (!$event->isApplicable($type) ) {
                continue;
            }

            switch ($type) {
                case 'company.segmentadd':
                    $this->timelineSegmentAdd($event, $type, $name);
                    break;

                case 'company.segmentremove':
                    $this->timelineSegmentRemove($event, $type, $name);
                    break;

            }
        }
    }

    private function timelineSegmentAdd(CompanyTimelineEvent $event, string $eventType, string $eventTypeName): void
    {
        $company = $event->getCompany();
        $resultsSegmentAdded = $this->companyEventLogModel->getRepository()->findBy([
            'company' => $company,
            'object'   => 'company_segment',
            'action'   => 'added',
        ]);

        if (!empty($resultsSegmentAdded)) {
            foreach ($resultsSegmentAdded as $log) {
                $dateAdded = $log->getDateAdded();
                $companySegment = $this->companySegmentModel->getRepository()->find($log->getObjectId());
                $companySegmentName = 'Unknown Segment';
                if ($companySegment) {
                    $companySegmentName = $companySegment->getName();
                }
                $eventName = $this->translator->trans('mautic.company_segments.timeline.segment.add', [
                    '%segment%' => $companySegmentName,
                ]);

                $eventSegmentLabelName = $this->translator->trans('mautic.company_segments.timeline.segment_label_name', [
                    '%segment%' => $companySegmentName,
                ]);
                $eventLabel = [
                    'label' => $eventSegmentLabelName,
                    'href'  => $this->router->generate(
                        'mautic_company_segments_action',
                        [
                            'objectAction' => 'view',
                            'objectId'     => $log->getObjectId(),
                        ]
                    ),
                ];
                $event->addEvent(
                    [
                        'event'         => $eventType,
                        'eventId'       => $eventType.$event->getCompany()->getId(),
                        'icon'          => 'fa ri-fw ri-time-line',
                        'eventType'     => $eventName,
                        'eventLabel'     => $eventLabel,
                        'eventPriority' => -5, // Usually something happened to create the lead so this should display afterward
                        'timestamp'     => $dateAdded,

                    ]
                );
            }
        }
    }

    private function timelineSegmentRemove(CompanyTimelineEvent $event, string $eventType, string $eventTypeName)
    {
        $company = $event->getCompany();
        $resultsSegmentRemoved = $this->companyEventLogModel->getRepository()->findBy([
            'company' => $company,
            'object'   => 'company_segment',
            'action'   => 'removed',
        ]);

        if (!empty($resultsSegmentRemoved)) {
            foreach ($resultsSegmentRemoved as $log) {
                $dateAdded = $log->getDateAdded();
                $companySegment = $this->companySegmentModel->getRepository()->find($log->getObjectId());
                $companySegmentName = 'Unknown Segment';
                if ($companySegment) {
                   $companySegmentName = $companySegment->getName();
                }
                $eventName = $this->translator->trans('mautic.company_segments.timeline.segment.remove', [
                    '%segment%' => $companySegmentName,
                ]);

                $eventSegmentLabelName = $this->translator->trans('mautic.company_segments.timeline.segment_label_name', [
                    '%segment%' => $companySegmentName,
                ]);
                $eventLabel = [
                    'label' => $eventSegmentLabelName,
                    'href'  => $this->router->generate(
                        'mautic_company_segments_action',
                        [
                            'objectAction' => 'view',
                            'objectId'     => $log->getObjectId(),
                        ]
                    ),
                ];
                $event->addEvent(
                    [
                        'event'         => $eventType,
                        'eventId'       => $eventType.$event->getCompany()->getId(),
                        'icon'          => 'fa ri-fw ri-time-line',
                        'eventType'     => $eventName,
                        'eventLabel'     => $eventLabel,
                        'eventPriority' => -5, // Usually something happened to create the lead so this should display afterward
                        'timestamp'     => $dateAdded,

                    ]
                );
            }
        }
    }
}