<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type;

use Mautic\LeadBundle\Form\Type\FilterPropertiesType;
use Mautic\LeadBundle\Provider\FormAdjustmentsProviderInterface;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FilterType extends AbstractType
{
    public function __construct(
        private FormAdjustmentsProviderInterface $formAdjustmentsProvider,
        private CompanySegmentModel $companySegmentModel
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldChoices = $this->companySegmentModel->getChoiceFields();

        $builder->add(
            'glue',
            ChoiceType::class,
            [
                'label'   => false,
                'choices' => [
                    'mautic.lead.list.form.glue.and' => 'and',
                    'mautic.lead.list.form.glue.or'  => 'or',
                ],
                'attr' => [
                    'class'    => 'form-control not-chosen glue-select',
                    'onchange' => 'Mautic.updateFilterPositioning(this)',
                ],
            ]
        );

        $formModifier = function (FormEvent $event) use ($fieldChoices): void {
            $data        = (array) $event->getData();
            $form        = $event->getForm();
            $fieldAlias  = $data['field'] ?? null;
            \assert(is_string($fieldAlias) || null === $fieldAlias);
            $fieldObject = $data['object'] ?? 'behaviors';
            // Looking for behaviors for BC reasons as some filters were moved from 'lead' to 'behaviors'.
            $field       = $fieldChoices[$fieldObject][$fieldAlias] ?? $fieldChoices['behaviors'][$fieldAlias] ?? null;

            $operators = [];
            if (is_array($field) && is_array($field['operators'])) {
                $operators = $field['operators'];
            }

            $operator = $data['operator'] ?? null;

            if ([] !== $operators && !$operator) {
                $operator = array_key_first($operators);
            }

            $form->add(
                'operator',
                ChoiceType::class,
                [
                    'label'   => false,
                    'choices' => $operators,
                    'attr'    => [
                        'class'    => 'form-control not-chosen',
                        'onchange' => 'Mautic.convertCompanySegmentFilterInput(this)',
                    ],
                ]
            );

            $form->add(
                'properties',
                FilterPropertiesType::class,
                [
                    'label' => false,
                ]
            );

            if (!is_array($field)) {
                // The field was probably deleted since the segment was created.
                // Do not show up the filter based on a deleted field.
                return;
            }

            $filterPropertiesType = $form->get('properties');
            $filterPropertiesType->setData($data['properties'] ?? []);

            if (null !== $fieldAlias && '' !== $fieldAlias && null !== $operator && '' !== $operator) {
                $this->formAdjustmentsProvider->adjustForm(
                    $filterPropertiesType,
                    $fieldAlias,
                    $fieldObject,
                    $operator,
                    $field
                );
            }
        };

        $builder->addEventListener(FormEvents::PRE_SET_DATA, $formModifier);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, $formModifier);
        $builder->add('field', HiddenType::class);
        $builder->add('object', HiddenType::class);
        $builder->add('type', HiddenType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'label'          => false,
                'error_bubbling' => false,
            ]
        );
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['fields'] = $this->companySegmentModel->getChoiceFields();
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'leadlist_filter';
    }
}
