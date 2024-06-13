<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Mautic\LeadBundle\Event\FormAdjustmentEvent;
use Mautic\LeadBundle\Event\ListFieldChoicesEvent;
use Mautic\LeadBundle\Exception\ChoicesNotFoundException;
use Mautic\LeadBundle\Helper\FormFieldHelper;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Provider\FieldChoicesProviderInterface;
use Mautic\LeadBundle\Provider\TypeOperatorProviderInterface;
use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentFiltersChoicesEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TypeOperatorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private \Mautic\LeadBundle\EventListener\TypeOperatorSubscriber $typeOperatorSubscriber,
        private LeadFieldRepository $leadFieldRepository,
        private CompanySegmentModel $companySegmentModel,
        private TypeOperatorProviderInterface $typeOperatorProvider,
        private FieldChoicesProviderInterface $fieldChoicesProvider,
        private TranslatorInterface $translator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::COLLECT_FILTER_CHOICES_FOR_LIST_FIELD_TYPE => ['onTypeListCollect', 0],
            LeadEvents::ADJUST_FILTER_FORM_TYPE_FOR_FIELD          => [
                ['onSegmentFilterFormHandleSelect', 400],
            ],
            CompanySegmentEvents::SEGMENT_FILTERS_CHOICES_ON_GENERATE => [
                ['onGenerateSegmentFiltersAddStaticFields', 0],
                ['onGenerateSegmentFiltersAddCustomFields', 0],
            ],
        ];
    }

    public function onGenerateSegmentFiltersAddStaticFields(CompanySegmentFiltersChoicesEvent $event): void
    {
        $this->setIncludeExcludeOperatorsToTextFilters($event);
        $staticFields = [
            'date_added' => [
                'label'      => $this->translator->trans('mautic.core.date.added'),
                'properties' => ['type' => 'date'],
                'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('default'),
                'object'     => 'company',
            ],
            'date_modified' => [
                'label'      => $this->translator->trans('mautic.lead.list.filter.date_modified'),
                'properties' => ['type' => 'datetime'],
                'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('default'),
                'object'     => 'company',
            ],
            CompanySegmentModel::PROPERTIES_FIELD => [
                'label'      => $this->translator->trans('mautic.company_segments.filter.lists'),
                'properties' => [
                    'type' => CompanySegmentModel::PROPERTIES_FIELD,
                    'list' => $this->fieldChoicesProvider->getChoicesForField('multiselect', CompanySegmentModel::PROPERTIES_FIELD, $event->getSearch()),
                ],
                'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('multiselect'),
                'object'     => 'company',
            ],
        ];

        foreach ($staticFields as $alias => $fieldOptions) {
            // label is defined as mautic.lead.company_segments
            $event->addChoice(CompanySegmentModel::PROPERTIES_FIELD, $alias, $fieldOptions);
        }
    }

    public function onGenerateSegmentFiltersAddCustomFields(CompanySegmentFiltersChoicesEvent $event): void
    {
        $this->leadFieldRepository->getListablePublishedFields()->filter(static function (LeadField $leadField): bool {
            return 'company' === $leadField->getObject();
        })->map(function (LeadField $field) use ($event): void {
            $type               = $field->getType();
            $properties         = $field->getProperties();
            $properties['type'] = $type;

            if ('boolean' === $type) {
                $properties['list'] = [
                    $properties['no']  => 0,
                    $properties['yes'] => 1,
                ];
            } elseif (in_array($type, ['select', 'multiselect'], true)) {
                $properties['list'] = FormFieldHelper::parseListForChoices($properties['list'] ?? []);
            } else {
                try {
                    $properties['list'] = $this->fieldChoicesProvider->getChoicesForField($type, $field->getAlias());
                } catch (ChoicesNotFoundException) {
                    // That's fine. Not all fields should have choices.
                }
            }

            $event->addChoice(
                $field->getObject(),
                $field->getAlias(),
                [
                    'label'      => $field->getLabel(),
                    'properties' => $properties,
                    'object'     => $field->getObject(),
                    'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType($type),
                ]
            );
        });
    }

    public function onTypeListCollect(ListFieldChoicesEvent $event): void
    {
        $items     = $this->companySegmentModel->getCompanySegments();
        $labelName = 'name';
        $keyName   = 'id';

        $choices = [];

        foreach ($items as $item) {
            $choices[$item[$labelName]] = $item[$keyName];
        }

        $event->setChoicesForFieldAlias(CompanySegmentModel::PROPERTIES_FIELD, $choices);
    }

    public function onSegmentFilterFormHandleSelect(FormAdjustmentEvent $event): void
    {
        $fieldDetails = $event->getFieldDetails();

        if (!is_array($fieldDetails['properties']) || 'company_segments' !== $fieldDetails['properties']['type']) {
            return;
        }

        $fieldDetails['properties']['type'] = 'leadlist';

        $changedEvent = new FormAdjustmentEvent(
            $event->getForm(),
            $event->getFieldAlias(),
            $event->getFieldObject(),
            $event->getOperator(),
            $fieldDetails
        );

        $this->typeOperatorSubscriber->onSegmentFilterFormHandleSelect($changedEvent);

        if ($changedEvent->isPropagationStopped()) {
            $event->stopPropagation();
        }
    }

    private function setIncludeExcludeOperatorsToTextFilters(CompanySegmentFiltersChoicesEvent $event): void
    {
        $choices = $event->getChoices();

        foreach ($choices as $group => $groups) {
            foreach ($groups as $alias => $choice) {
                $type = null;
                if (is_array($choice) && is_array($choice['properties'])) {
                    $type = $choice['properties']['type'] ?? null;
                }

                if ('text' === $type) {
                    if (!is_array($choices[$group][$alias])) {
                        $choices[$group][$alias] = [];
                    }

                    $choices[$group][$alias]['operators'] = $this->typeOperatorProvider->getOperatorsIncluding([
                        OperatorOptions::EQUAL_TO,
                        OperatorOptions::NOT_EQUAL_TO,
                        OperatorOptions::EMPTY,
                        OperatorOptions::NOT_EMPTY,
                        OperatorOptions::LIKE,
                        OperatorOptions::NOT_LIKE,
                        OperatorOptions::REGEXP,
                        OperatorOptions::NOT_REGEXP,
                        OperatorOptions::IN,
                        OperatorOptions::NOT_IN,
                        OperatorOptions::STARTS_WITH,
                        OperatorOptions::ENDS_WITH,
                        OperatorOptions::CONTAINS,
                    ]);
                }
            }
        }

        $event->setChoices($choices);
    }
}
