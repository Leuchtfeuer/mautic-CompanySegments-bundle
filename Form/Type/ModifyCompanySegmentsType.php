<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ModifyCompanySegmentsType extends AbstractType
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'add_segments',
            CompanySegmentListType::class,
            [
                'label' => 'mautic.modify_company_segments.form.add',
                'attr'  => [
                    'data-placeholder'     => $this->translator->trans('mautic.core.form.choosemultiple'),
                ],
                'multiple'        => true,
            ]
        );

        $builder->add(
            'remove_segment',
            CompanySegmentListType::class,
            [
                'label' => 'mautic.modify_company_segments.form.remove',
                'attr'  => [
                    'data-placeholder'     => $this->translator->trans('mautic.core.form.choosemultiple'),
                ],
                'multiple'        => true,
            ]
        );
    }
}