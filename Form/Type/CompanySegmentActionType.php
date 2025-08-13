<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @phpstan-ignore-next-line
 *
 * @extends AbstractType<mixed>
 */
class CompanySegmentActionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'addToLists',
            CompanySegmentListType::class,
            [
                'label'      => 'mautic.company_segments.events.addtolists',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class' => 'form-control',
                ],
                'multiple' => true,
                'expanded' => false,
            ]
        );

        $builder->add(
            'removeFromLists',
            CompanySegmentListType::class,
            [
                'label'      => 'mautic.company_segments.events.removefromlists',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class' => 'form-control',
                ],
                'multiple' => true,
                'expanded' => false,
            ]
        );
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'companysegment_action';
    }
}
