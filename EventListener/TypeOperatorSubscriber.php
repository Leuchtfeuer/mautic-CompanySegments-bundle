<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Mautic\LeadBundle\Event\FormAdjustmentEvent;
use Mautic\LeadBundle\Event\LeadListFiltersChoicesEvent;
use Mautic\LeadBundle\Event\ListFieldChoicesEvent;
use Mautic\LeadBundle\Exception\ChoicesNotFoundException;
use Mautic\LeadBundle\Helper\FormFieldHelper;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\CompanyModel;
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
        private CompanyModel $companyModel,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::COLLECT_FILTER_CHOICES_FOR_LIST_FIELD_TYPE => [
                ['onCompanySegmentTypeListCollect', 0],
                ['onCompaniesTypeListCollect', 0]
            ],
            LeadEvents::ADJUST_FILTER_FORM_TYPE_FOR_FIELD          => [
                ['onSegmentFilterFormHandleSelect', 400],
            ],
            CompanySegmentFiltersChoicesEvent::class => [
                ['onGenerateCompanySegmentStaticFields', 0],
                ['onUpdateGenerateFieldsWithDefaultLeadFieldsToCompanySegment', 0],
            ],
            LeadEvents::LIST_FILTERS_CHOICES_ON_GENERATE => [
                ['updateGenerateSegmentFiltersAddStaticFields', 0],
                ['onUpdateGenerateFieldsWithDefaultLeadFieldsToLeadSegment', 0],
            ],
        ];
    }

    private function addStaticCompanyFields(object $event, ?string $search = null): void
    {
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

    /**
     * Add the company segments multiselect field.
     *
     * @param object $event
     * @param string|null $search
     */
    private function addCompanySegmentListField(object $event, ?string $search = null): void
    {
        $companySegmentFieldOptions = [
            'label'      => $this->translator->trans('mautic.company_segments.filter.lists'),
            'properties' => [
                'type' => CompanySegmentModel::PROPERTIES_FIELD,
                'list' => $this->fieldChoicesProvider->getChoicesForField('multiselect', CompanySegmentModel::PROPERTIES_FIELD, $search),
            ],
            'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('multiselect'),
            'object'     => 'company',
        ];

        $event->addChoice(CompanySegmentModel::PROPERTIES_FIELD, CompanySegmentModel::PROPERTIES_FIELD, $companySegmentFieldOptions);
    }

    private function addAnyCompanyContactField(object $event, ?string $search = null): void
    {
        $contactCompanySegmentFieldOptions = [
            'label'      => $this->translator->trans('mautic.company_segments.filter.contacts.company.contact.membership'),
            'properties' => [
                'type' => 'leadlist',
                'list' => $this->fieldChoicesProvider->getChoicesForField('multiselect', 'leadlist', $search),
            ],
            'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('multiselect'),
            'object'     => 'company',
        ];

        $event->addChoice(
            'any_company_contact',
            'any_company_contact',
            $contactCompanySegmentFieldOptions
        );
    }

    public function onGenerateCompanySegmentStaticFields(CompanySegmentFiltersChoicesEvent $event): void
    {
        $this->setIncludeExcludeOperatorsToTextFiltersToCompanySegment($event);
        $this->addStaticCompanyFields($event, $event->getSearch());
        $this->addCompanySegmentListField($event, $event->getSearch());
        $this->addAnyCompanyContactField($event, $event->getSearch());

    }

    public function updateGenerateSegmentFiltersAddStaticFields(LeadListFiltersChoicesEvent $event): void
    {
        $this->setIncludeExcludeOperatorsToTextFiltersToLeadSegment($event);
        $this->addStaticCompanyFields($event, $event->getSearch());
        $this->addCompanySegmentListField($event, $event->getSearch());
    }

    public function onUpdateGenerateFieldsWithDefaultLeadFieldsToCompanySegment(CompanySegmentFiltersChoicesEvent $event): void
    {
        $this->onUpdateGenerateFieldsWithDefaultLeadFields($event);
    }

    public function onUpdateGenerateFieldsWithDefaultLeadFieldsToLeadSegment(LeadListFiltersChoicesEvent $event): void
    {
        $this->onUpdateGenerateFieldsWithDefaultLeadFields($event);
    }

    private function onUpdateGenerateFieldsWithDefaultLeadFields($event): void
    {
        $fields = $this->leadFieldRepository->getListablePublishedFields();

        if ($fields->isEmpty()) {
            // nothing to process
            return;
        }

        foreach ($fields as $field) {
            if (!$field instanceof LeadField) {
                continue;
            }

            if ('company' !== $field->getObject()) {
                continue;
            }

            $type       = $field->getType();
            $properties = $field->getProperties() ?? [];
            $properties['type'] = $type;

            if ('boolean' === $type) {
                $noKey  = $properties['no']  ?? 'no';
                $yesKey = $properties['yes'] ?? 'yes';
                $properties['list'] = [
                    $noKey  => 0,
                    $yesKey => 1,
                ];
            } elseif (in_array($type, ['select', 'multiselect'], true)) {
                $properties['list'] = FormFieldHelper::parseListForChoices($properties['list'] ?? []);
            } else {
                try {
                    $properties['list'] = $this->fieldChoicesProvider->getChoicesForField($type, $field->getAlias());
                } catch (ChoicesNotFoundException) {
                    // Not all fields have choices; ignore if missing
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
        }
    }

    public function onCompanySegmentTypeListCollect(ListFieldChoicesEvent $event): void
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

    public function onCompaniesTypeListCollect(ListFieldChoicesEvent $event): void
    {
        $items     = $this->companyModel->getEntities();
        $choices = [];
        foreach ($items as $item) {
            assert($item instanceof Company);
            $choices[$item->getName()] = $item->getName();
        }
        $event->setChoicesForFieldAlias('companies', $choices);
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
     * @param array <string, array<string, array<string, mixed>>> $choices
     * @param array <int,string>                                  $groupAllow
     *
     * @return array <string, array<string, array<string, mixed>>>
     */
    private function setIncludeExcludeOperatorsToTextFilters(array $choices, array $groupAllow =[]): array
    {
        foreach ($choices as $group => $groups) {
            if ([] !== $groupAllow && !in_array($group, $groupAllow, true)) {
                continue;
            }
            if (!is_array($groups)) {
                continue;
            }
            foreach ($groups as $alias => $choice) {
                $type = null;
                if (is_array($choice) && is_array($choice['properties'])) {
                    $type = $choice['properties']['type'] ?? null;
                }
                if ('text' === $type) {
                    assert(is_array($choices[$group]));
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

        return $choices;
    }
}
