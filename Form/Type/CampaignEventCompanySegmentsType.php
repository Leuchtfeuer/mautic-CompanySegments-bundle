<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class CampaignEventCompanySegmentsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'companySegments',
            CompanySegmentListType::class,
            [
                'global_only' => true,
                'label'       => 'mautic.company_segments.event.lists',
                'label_attr'  => ['class' => 'control-label'],
                'multiple'    => true,
                'required'    => false,
            ]
        );
    }

    public function getBlockPrefix()
    {
        return 'campaignevent_company_segments';
    }
}
