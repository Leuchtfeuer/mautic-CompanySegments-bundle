<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Validator\Constraints;

use Mautic\LeadBundle\Segment\ContactSegmentFilterFactory;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentDateValidator extends ConstraintValidator
{
    public function __construct(
        private ContactSegmentFilterFactory $contactSegmentFilterFactory,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * @phpstan-param array<mixed>|mixed $filters
     */
    public function validate($filters, Constraint $constraint): void
    {
        if (!is_array($filters)) {
            return;
        }

        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }

            if (isset($filter['type']) && in_array($filter['type'], ['date', 'datetime'], true)) {
                $segmentFilter  = $this->contactSegmentFilterFactory->factorSegmentFilter($filter);
                $parameterValue = $segmentFilter->getParameterValue();

                if (is_array($parameterValue)) {
                    continue;
                }

                if (!isset($filter['operator']) || !is_string($filter['operator'])) {
                    continue;
                }

                if (in_array($filter['operator'], ['regexp', '!regexp', 'like', '!like', 'startsWith', 'endsWith', 'contains'], true)) {
                    continue;
                }

                if (!is_string($parameterValue)) {
                    continue;
                }

                if (str_contains($parameterValue, '%')) {
                    return;
                }

                $formats  = ['Y-m-d', 'Y-m-d H:i', 'Y-m-d H:i:s'];
                $dateTime = false;

                foreach ($formats as $fmt) {
                    $dateTime = \DateTime::createFromFormat($fmt, $parameterValue);
                    if (false !== $dateTime) {
                        break;
                    }
                }

                if (false === $dateTime) {
                    $this->context->addViolation($this->translator->trans('mautic.lead.segment.date_invalid', ['%value%' => $parameterValue], 'validators'));

                    return;
                }
            }
        }
    }
}
