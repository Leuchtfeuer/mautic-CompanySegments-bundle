<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class SegmentDate extends Constraint
{
    public string $message;
}
