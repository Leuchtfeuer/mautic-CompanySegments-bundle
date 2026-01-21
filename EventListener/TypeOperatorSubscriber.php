<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Mautic\LeadBundle\Event\FormAdjustmentEvent;
use Mautic\LeadBundle\Event\LeadListFiltersChoicesEvent;
use Mautic\LeadBundle\Event\ListFieldChoicesEvent;
use Mautic\LeadBundle\Exception\ChoicesNotFoundException;
use Mautic\LeadBundle\Helper\FormFieldHelper;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Provider\FieldChoicesProviderInterface;
use Mautic\LeadBundle\Provider\TypeOperatorProviderInterface;
use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentFiltersChoicesEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see \Mautic\LeadBundle\EventListener\TypeOperatorSubscriber
 */
class TypeOperatorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private \Mautic\LeadBundle\EventListener\TypeOperatorSubscriber $typeOperatorSubscriber,
        private LeadFieldRepository $leadFieldRepository,
        private CompanySegmentModel $companySegmentModel,
        private TypeOperatorProviderInterface $typeOperatorProvider,
        private FieldChoicesProviderInterface $fieldChoicesProvider,
        private TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::COLLECT_FILTER_CHOICES_FOR_LIST_FIELD_TYPE => ['onTypeListCollect', 0],
            LeadEvents::ADJUST_FILTER_FORM_TYPE_FOR_FIELD          => [
                ['onSegmentFilterFormHandleSelect', 400],
            ],
            CompanySegmentFiltersChoicesEvent::class => [
                ['onGenerateSegmentFiltersAddStaticFields', 0],
                ['onGenerateSegmentFiltersAddCustomFields', 0],
            ],
            LeadEvents::LIST_FILTERS_CHOICES_ON_GENERATE => [
                ['updateGenerateSegmentFiltersAddStaticFieldsToLeadSegment', 0],
                ['updateGenerateSegmentFiltersAddCustomFieldsToLeadSegment', 0],
            ],
        ];
    }

    public function onGenerateSegmentFiltersAddStaticFields(CompanySegmentFiltersChoicesEvent $event): void
    {
        $this->setIncludeExcludeOperatorsToTextFiltersToCompanySegment($event);
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
        ];

        foreach ($staticFields as $alias => $fieldOptions) {
            // label is defined as mautic.lead.company_segments
            $event->addChoice('company', $alias, $fieldOptions);
        }

        $companySegmentFieldOptions = [
            'label'      => $this->translator->trans('mautic.company_segments.filter.lists'),
            'properties' => [
                'type' => CompanySegmentModel::PROPERTIES_FIELD,
                'list' => $this->fieldChoicesProvider->getChoicesForField('multiselect', CompanySegmentModel::PROPERTIES_FIELD, $event->getSearch()),
            ],
            'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('multiselect'),
            'object'     => 'company',
        ];
        $event->addChoice(CompanySegmentModel::PROPERTIES_FIELD, CompanySegmentModel::PROPERTIES_FIELD, $companySegmentFieldOptions);

        $leadSegmentMembership = [
            'label'      => $this->translator->trans('mautic.company_segments.filter.lead_lists'),
            'properties' => [
                'type' => 'leadlist',
                'list' => $this->fieldChoicesProvider->getChoicesForField('multiselect', 'leadlist'),
            ],
            'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('multiselect'),
        ];
        $event->addChoice('any_companycontact', 'contactsegmentmembership', $leadSegmentMembership);
    }

    public function updateGenerateSegmentFiltersAddStaticFieldsToLeadSegment(LeadListFiltersChoicesEvent $event): void
    {
        $this->setIncludeExcludeOperatorsToTextFiltersToLeadSegment($event);
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
        ];

        foreach ($staticFields as $alias => $fieldOptions) {
            // label is defined as mautic.lead.company_segments
            $event->addChoice('company', $alias, $fieldOptions);
        }

        $companySegmentFieldOptions = [
            'label'      => $this->translator->trans('mautic.company_segments.filter.lists'),
            'properties' => [
                'type' => CompanySegmentModel::PROPERTIES_FIELD,
                'list' => $this->fieldChoicesProvider->getChoicesForField('multiselect', CompanySegmentModel::PROPERTIES_FIELD, $event->getSearch()),
            ],
            'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('multiselect'),
            'object'     => 'company',
        ];
        $event->addChoice(CompanySegmentModel::PROPERTIES_FIELD, CompanySegmentModel::PROPERTIES_FIELD, $companySegmentFieldOptions);
    }

    public function updateGenerateSegmentFiltersAddCustomFieldsToLeadSegment(LeadListFiltersChoicesEvent $event): void
    {
        $this->leadFieldRepository->getListablePublishedFields()->filter(static function (LeadField $leadField): bool {
            return 'company' === $leadField->getObject();
        })->map(function (LeadField $field) use ($event): void {
            $type               = $field->getType();
            $properties         = $field->getProperties();
            $properties['type'] = $type;

            if ('boolean' === $type) {
                $no  = 'no';
                $yes = 'yes';
                if (array_key_exists('no', $properties) && is_string($properties['no'])) {
                    $no = $properties['no'];
                }
                if (array_key_exists('yes', $properties) && is_string($properties['yes'])) {
                    $yes = $properties['yes'];
                }
                $properties['list'] = [
                    $no  => 0,
                    $yes => 1,
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

    public function onGenerateSegmentFiltersAddCustomFields(CompanySegmentFiltersChoicesEvent $event): void
    {
        $this->leadFieldRepository->getListablePublishedFields()->filter(static function (LeadField $leadField): bool {
            return 'company' === $leadField->getObject();
        })->map(function (LeadField $field) use ($event): void {
            $type               = $field->getType();
            $properties         = $field->getProperties();
            $properties['type'] = $type;

            if ('boolean' === $type) {
                $no  = 'no';
                $yes = 'yes';
                if (array_key_exists('no', $properties) && is_string($properties['no'])) {
                    $no = $properties['no'];
                }
                if (array_key_exists('yes', $properties) && is_string($properties['yes'])) {
                    $yes = $properties['yes'];
                }
                $properties['list'] = [
                    $no  => 0,
                    $yes => 1,
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

    private function setIncludeExcludeOperatorsToTextFiltersToCompanySegment(CompanySegmentFiltersChoicesEvent $event): void
    {
        $choices = $event->getChoices();
        $choices = $this->setIncludeExcludeOperatorsToTextFilters($choices);
        // @phpstan-ignore-next-line
        $event->setChoices($choices);
    }

    private function setIncludeExcludeOperatorsToTextFiltersToLeadSegment(LeadListFiltersChoicesEvent $event): void
    {
        $choices = $event->getChoices();
        // Ensure $choices matches the expected structure
        if (!is_array($choices)) {
            $choices = [];
        }
        $choices = $this->setIncludeExcludeOperatorsToTextFilters($choices, ['company']);
        // @phpstan-ignore-next-line
        $event->setChoices($choices);
    }

    /**
     * Normalize and validate incoming choices to ensure string keys and array values,
     * then add include/exclude operators to text fields.
     *
     * @param array<mixed>      $choices
     * @param array<int,string> $groupAllow
     *
     * @return array<mixed>
     */
    private function setIncludeExcludeOperatorsToTextFilters(array $choices, array $groupAllow = []): array
    {
        // Normalize to array<string, array<string, array<string,mixed>>>
        $normalized = [];

        foreach ($choices as $group => $groups) {
            if (!is_string($group) || !is_array($groups)) {
                continue;
            }

            foreach ($groups as $alias => $choice) {
                if (!is_string($alias) || !is_array($choice)) {
                    continue;
                }

                $normalized[$group][$alias] = $choice;
            }
        }

        foreach ($normalized as $group => $groups) {
            if ([] !== $groupAllow && !in_array($group, $groupAllow, true)) {
                continue;
            }

            foreach ($groups as $alias => $choice) {
                $type = null;
                if (isset($choice['properties']) && is_array($choice['properties'])) {
                    $type = $choice['properties']['type'] ?? null;
                }

                if ('text' === $type) {
                    if (!array_key_exists($group, $normalized) || !is_array($normalized[$group])) {
                        $normalized[$group] = [];
                    }
                    if (!isset($normalized[$group][$alias]) || !is_array($normalized[$group][$alias])) {
                        $normalized[$group][$alias] = [];
                    }

                    $normalized[$group][$alias]['operators'] = $this->typeOperatorProvider->getOperatorsIncluding([
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

        return $normalized;
    }
}
