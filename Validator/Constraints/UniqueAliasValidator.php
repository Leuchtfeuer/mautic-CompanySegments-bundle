<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Validator\Constraints;

use Mautic\CoreBundle\Helper\UserHelper;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class UniqueAliasValidator extends ConstraintValidator
{
    public function __construct(public CompanySegmentRepository $companySegmentRepository, public UserHelper $userHelper)
    {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof CompanySegment) {
            throw new UnexpectedValueException($value, CompanySegment::class);
        }

        if (!$constraint instanceof UniqueAlias) {
            throw new UnexpectedTypeException($constraint, UniqueAlias::class);
        }

        $field = $constraint->field;

        //        if ('' === $field) {
        //            throw new ConstraintDefinitionException('A field has to be specified.');
        //        }

        $alias = $value->getAlias();
        if (null === $alias || '' === $alias) {
            return;
        }

        $segments = $this->companySegmentRepository->getSegments(
            $this->userHelper->getUser(),
            $alias,
            $value->getId()
        );

        if (0 === count($segments)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->atPath($field)
            ->addViolation();
    }
}
