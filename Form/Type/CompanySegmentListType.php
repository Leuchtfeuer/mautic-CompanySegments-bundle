<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type;

use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
class CompanySegmentListType extends AbstractType
{
    public function __construct(
        private CompanySegmentModel $companySegmentModel,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => function (Options $options): array {
                $listsCompanySegments = (empty($options['global_only'])) ? $this->companySegmentModel->getEntities() : $this->companySegmentModel->getEntities();
                $listsCompanySegments = (empty($options['preference_center_only'])) ? $listsCompanySegments : $listsCompanySegments;

                $choices = [];
                foreach ($listsCompanySegments as $companySegment) {
                    if (empty($options['preference_center_only'])) {
                        $choices[$companySegment->getName()] = $companySegment->getId();
                    } else {
                        $choices[empty($companySegment->getPublicName()) ? $companySegment->getName() : $companySegment->getPublicName()] = $companySegment->getId();
                    }
                }

                return $choices;
            },
            'global_only'            => false,
            'preference_center_only' => false,
            'required'               => false,
        ]);
    }

    /**
     * @return string
     */
    public function getParent()
    {
        return ChoiceType::class;
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'companysegment_choices';
    }
}
