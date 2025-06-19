<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanyTimelineEvent;

trait TimelineCompanyEventLogTrait
{

    private function addEvents(CompanyTimelineEvent $event, $eventType, $eventTypeName, $icon, $bundle = null, $object = null, $action = null, $contentTemplate = null): void
    {
        $eventTypeName = $this->translator->trans($eventTypeName);
        $event->addEventType($eventType, $eventTypeName);

        if (!$event->isApplicable($eventType)) {
            return;
        }

        $events = $this->companyEventLogRepository->getEvents($event->getCompany(), $bundle, $object, $action, $event->getQueryOptions());

        // Add to counter
        $event->addToCounter($eventType, $events);

        if ($event->isEngagementCount()) {
            return;
        }

        // Add the logs to the event array
        foreach ($events['results'] as $log) {
            $event->addEvent(
                $this->getEventEntry($log, $eventType, $eventTypeName, $icon, $contentTemplate)
            );
        }
    }

    private function addCompanySegmentEvents(CompanyTimelineEvent $event, $eventType, $eventTypeName, $icon, $bundle = null, $object = null, $action = null, $contentTemplate = null): void
    {
        $eventTypeName = $this->translator->trans($eventTypeName);
        $event->addEventType($eventType, $eventTypeName);

        if (!$event->isApplicable($eventType)) {
            return;
        }

        $events = $this->companyEventLogRepository->getEvents($event->getCompany(), $bundle, $object, $action, $event->getQueryOptions());

        // Add to counter
        $event->addToCounter($eventType, $events);

        if ($event->isEngagementCount()) {
            return;
        }

        // Add the logs to the event array
        foreach ($events['results'] as $log) {
            $companySegment     = $this->companySegmentModel->getRepository()->find($log['object_id']);
            $companySegmentName = 'Unknown Segment';
            if ($companySegment) {
                $companySegmentName = $companySegment->getName();
            }
            $eventName = $this->translator->trans('mautic.company_segments.timeline.segment.add', [
                '%segment%' => $companySegmentName,
            ]);

            if (!empty($action) && 'removed' === $action) {
                $eventName = $this->translator->trans('mautic.company_segments.timeline.segment.remove', [
                    '%segment%' => $companySegmentName,
                ]);
            }

            $eventSegmentLabelName = $this->translator->trans('mautic.company_segments.timeline.segment_label_name', [
                '%segment%' => $companySegmentName,
            ]);
            $eventLabel = [
                'label' => $eventSegmentLabelName,
                'href'  => $this->router->generate(
                    'mautic_company_segments_action',
                    [
                        'objectAction' => 'view',
                        'objectId'     => $log['object_id'],
                    ]
                ),
            ];

            $event->addEvent(
                $this->getEventEntry($log, $eventType, $eventName, $icon, $contentTemplate, $eventLabel)
            );
        }
    }

    private function getEventEntry(array $log, string $eventType, $eventTypeName, $icon, $contentTemplate, $eventLabel = null): array
    {
        $properties = json_decode($log['properties'], true);
        if (null === $eventLabel) {
            $eventLabel = $this->getSourceName($log, $eventType);
        }

        $entry = [
            'event'      => $eventType,
            'eventId'    => $eventType.$log['id'],
            'eventType'  => $eventTypeName,
            'eventLabel' => $eventLabel,
            'timestamp'  => $log['date_added'],
            'icon'       => $icon,
            'contactId'  => $log['company_id'],
            'extra'      => $properties,
        ];

        if ($contentTemplate) {
            $entry['contentTemplate'] = $contentTemplate;
        }

        return $entry;
    }

    /**
     * @return string
     */
    private function getSourceName(array $log, string $eventType)
    {
        $properties = json_decode($log['properties'], true);

        if (!empty($properties['object_description'])) {
            $customString = 'mautic.company.timeline.'.$eventType.'_by_object';
            if ($this->translator->hasId($customString)) {
                return $this->translator->trans(
                    $customString,
                    [
                        '%name%' => $properties['object_description'],
                    ]
                );
            }

            $customString = 'mautic.company.timeline.'.$eventType.'_'.$log['action'].'_by_object';
            if ($this->translator->hasId($customString)) {
                return $this->translator->trans(
                    $customString,
                    [
                        '%name%' => $properties['object_description'],
                    ]
                );
            }
        }

        $customString = 'mautic.company.timeline.'.$log['bundle'].'.'.$log['object'];
        if ($this->translator->hasId($customString)) {
            return $this->translator->trans($customString);
        }

        $customString = 'mautic.company.timeline.'.$log['bundle'].'.'.$log['object'].'.'.$log['action'];
        if ($this->translator->hasId($customString)) {
            return $this->translator->trans($customString);
        }

        return $this->translator->trans(
            'mautic.company.timeline.'.$eventType,
            [
                '%bundle%' => $log['bundle'],
                '%object%' => $log['object'],
                '%action%' => $log['action'],
            ]
        );
    }
}
