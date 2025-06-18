<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLogRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanyTimelineEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\LeuchfeuerCompanySegmentsEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Mautic\CoreBundle\Translation\Translator;

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
//        $company             = $event->getCompany();
//        $resultsSegmentLogAdded = $this->companyEventLogModel->getRepository()->findBy([
//            'company'  => $event->getCompany(),
//            'object'   => 'company_segment',
//            'action'   => 'added',
//        ]);
//        $bundle              = 'company';
//        $object              = 'company_segment';
//        $action              = 'added';
//        $resultsSegmentLogAdded = $this->companyEventLogRepository->getEvents($event->getCompany(), $bundle, $object, $action, $event->getQueryOptions());

//        if (!empty($resultsSegmentLogAdded)) {
//            foreach ($resultsSegmentLogAdded as $log) {
////                $log = $this->companyEventLogModel->getRepository()->find($log['id']);
//                $dateAdded          = $log->getDateAdded();
//                $companySegment     = $this->companySegmentModel->getRepository()->find($log->getObjectId());
//                $companySegmentName = 'Unknown Segment';
//                if ($companySegment) {
//                    $companySegmentName = $companySegment->getName();
//                }
//                $eventName = $this->translator->trans('mautic.company_segments.timeline.segment.add', [
//                    '%segment%' => $companySegmentName,
//                ]);
//
//                $eventSegmentLabelName = $this->translator->trans('mautic.company_segments.timeline.segment_label_name', [
//                    '%segment%' => $companySegmentName,
//                ]);
//                $eventLabel = [
//                    'label' => $eventSegmentLabelName,
//                    'href'  => $this->router->generate(
//                        'mautic_company_segments_action',
//                        [
//                            'objectAction' => 'view',
//                            'objectId'     => $log->getObjectId(),
//                        ]
//                    ),
//                ];
//
//                $event->addEvent(
//                    [
//                        'event'          => $eventType,
//                        'eventId'        => $eventType.$event->getCompany()->getId(),
//                        'icon'           => 'fa ri-fw ri-time-line',
//                        'eventType'      => $eventName,
//                        'eventLabel'     => $eventLabel,
//                        'eventPriority'  => -5, // Usually something happened to create the lead so this should display afterward
//                        'timestamp'      => $dateAdded,
//                    ]
//                );
//            }
//        }
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

//        $company               = $event->getCompany();
//        $resultsSegmentLogRemoved = $this->companyEventLogModel->getRepository()->findBy([
//            'company'  => $event->getCompany(),
//            'object'   => 'company_segment',
//            'action'   => 'removed',
//        ]);


//        $bundle              = 'company';
//        $object              = 'company_segment';
//        $action              = 'removed';
//
//        $resultsSegmentLogRemoved = $this->companyEventLogRepository->getEvents($event->getCompany(), $bundle, $object, $action, $event->getQueryOptions());

//        if (!empty($resultsSegmentLogRemoved)) {
//            foreach ($resultsSegmentLogRemoved as $log) {
////                $log = $this->companyEventLogModel->getRepository()->find($logRemoved['id']);
//                $dateAdded          = $log->getDateAdded();
//                $companySegment     = $this->companySegmentModel->getRepository()->find($log->getObjectId());
//                $companySegmentName = 'Unknown Segment';
//                if ($companySegment) {
//                    $companySegmentName = $companySegment->getName();
//                }
//                $eventName = $this->translator->trans('mautic.company_segments.timeline.segment.remove', [
//                    '%segment%' => $companySegmentName,
//                ]);
//
//                $eventSegmentLabelName = $this->translator->trans('mautic.company_segments.timeline.segment_label_name', [
//                    '%segment%' => $companySegmentName,
//                ]);
//                $eventLabel = [
//                    'label' => $eventSegmentLabelName,
//                    'href'  => $this->router->generate(
//                        'mautic_company_segments_action',
//                        [
//                            'objectAction' => 'view',
//                            'objectId'     => $log->getObjectId(),
//                        ]
//                    ),
//                ];
//
//                $event->addEvent(
//                    [
//                        'event'          => $eventType,
//                        'eventId'        => $eventType.$event->getCompany()->getId(),
//                        'icon'           => 'fa ri-fw ri-time-line',
//                        'eventType'      => $eventName,
//                        'eventLabel'     => $eventLabel,
//                        'eventPriority'  => -5, // Usually something happened to create the lead so this should display afterward
//                        'timestamp'      => $dateAdded,
//                    ]
//                );
//            }
//        }
    }
}
