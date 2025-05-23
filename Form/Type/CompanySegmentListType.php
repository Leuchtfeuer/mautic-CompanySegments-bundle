<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type;

use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @phpstan-ignore-next-line
 *
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
                $listsCompanySegments = $this->companySegmentModel->getEntities();
                $choices              = [];
                foreach ($listsCompanySegments as $companySegment) {
                    assert($companySegment instanceof CompanySegment);
                    if (null === $options['preference_center_only'] || '' === $options['preference_center_only']) {
                        $choices[$companySegment->getName()] = $companySegment->getId();
                    } else {
                        $key = $companySegment->getName();
                        if (null !== $companySegment->getPublicName() && '' !== $companySegment->getPublicName()) {
                            $key = $companySegment->getPublicName();
                        }
                        $choices[$key] = $companySegment->getId();
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
