<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\DynamicContentFilterEntryFiltersType;
use Mautic\LeadBundle\Entity\RegexTrait;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Decorates DynamicContentFilterEntryFiltersType to add company_segments support
 * for Dynamic Content filters in the email builder.
 */
class DcEntryFiltersTypeDecorator extends DynamicContentFilterEntryFiltersType
{
    use RegexTrait;

    private TranslatorInterface $translatorLocal;

    /**
     * @var array<string, int>|null
     */
    private ?array $companySegmentChoices = null;

    public function __construct(
        TranslatorInterface $translator,
        ListModel $listModel,
        private CompanySegmentModel $companySegmentModel,
    ) {
        parent::__construct($translator, $listModel);
        $this->translatorLocal = $translator;
    }

    public function getBlockPrefix(): string
    {
        return 'dynamic_content_filter_entry_filters';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('companySegments', $this->getCompanySegmentChoices());
        $resolver->setDefined('companySegments');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event): void {
                $this->preProcessCompanySegments($event);
            },
            10
        );

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event): void {
                $this->preProcessCompanySegments($event);
            },
            10
        );

        $formModifier = function (FormEvent $event, string $eventName) use ($options): void {
            $this->buildFiltersForm($eventName, $event, $this->translatorLocal);
            $this->postProcessCompanySegments($event, $options);
        };

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($formModifier): void {
                $formModifier($event, FormEvents::PRE_SET_DATA);
            }
        );

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($formModifier): void {
                $formModifier($event, FormEvents::PRE_SUBMIT);
            }
        );

        $builder->add('field', HiddenType::class);
        $builder->add('object', HiddenType::class);
        $builder->add('type', HiddenType::class);
    }

    private function preProcessCompanySegments(FormEvent $event): void
    {
        $data = $event->getData();

        if (!is_array($data)) {
            return;
        }

        if (!isset($data['type']) || 'company_segments' !== $data['type']) {
            return;
        }

        $data['__original_type']     = 'company_segments';
        $data['__original_operator'] = $data['operator'] ?? null;
        $data['type']                = 'text';

        if (!isset($data['filter'])) {
            $data['filter'] = [];
        } elseif (!is_array($data['filter'])) {
            $data['filter'] = [$data['filter']];
        }

        $event->setData($data);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function postProcessCompanySegments(FormEvent $event, array $options): void
    {
        $data = $event->getData();

        if (!is_array($data)) {
            return;
        }

        if (!isset($data['__original_type']) || 'company_segments' !== $data['__original_type']) {
            return;
        }

        $form = $event->getForm();

        if ($form->has('filter')) {
            $form->remove('filter');
        }

        $operator = $data['__original_operator'] ?? $data['operator'] ?? '';
        \assert(is_string($operator));
        $multiple = in_array($operator, ['in', '!in'], true);

        $form->add(
            'filter',
            ChoiceType::class,
            [
                'label'                     => false,
                'attr'                      => ['class' => 'form-control filter-value'],
                'data'                      => $data['filter'] ?? ($multiple ? [] : ''),
                'choices'                   => $options['companySegments'],
                'multiple'                  => $multiple,
                'choice_translation_domain' => false,
                'error_bubbling'            => false,
            ]
        );

        $data['type'] = 'company_segments';
        unset($data['__original_type'], $data['__original_operator']);
        $event->setData($data);
    }

    /**
     * @return array<string, int>
     */
    private function getCompanySegmentChoices(): array
    {
        if (null === $this->companySegmentChoices) {
            $items   = $this->companySegmentModel->getCompanySegments();
            $choices = [];

            foreach ($items as $item) {
                \assert(is_array($item));
                $choices[$item['name']] = (int) $item['id'];
            }

            $this->companySegmentChoices = $choices;
        }

        return $this->companySegmentChoices;
    }
}
