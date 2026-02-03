<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Extension;

use Mautic\LeadBundle\Form\Type\LeadImportFieldType;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type\CompanySegmentListType;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Extends LeadImportFieldType to add company segments field for company imports.
 */
class LeadImportFieldTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private TranslatorInterface $translator,
        private Config $config,
    ) {
    }

    /** @phpstan-ignore-next-line */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        // Only add field for company imports
        if (!isset($options['object']) || 'company' !== $options['object']) {
            return;
        }

        $builder->add(
            'company_segments',
            CompanySegmentListType::class,
            [
                'label'      => 'mautic.company_segments.form.import.segments',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'                        => 'form-control',
                    'data-placeholder'             => $this->translator->trans('mautic.company_segments.form.import.segments.placeholder'),
                    'data-company-segments-import' => 'true',
                ],
                'required' => false,
                'multiple' => true,
                'mapped'   => false,
            ]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Ensure the 'object' option is available
        $resolver->setDefined(['object']);
    }

    public static function getExtendedTypes(): iterable
    {
        // Specify which form type this extension applies to
        return [LeadImportFieldType::class];
    }
}
