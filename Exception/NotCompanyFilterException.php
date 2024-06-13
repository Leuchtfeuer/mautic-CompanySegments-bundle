<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Exception;

use Mautic\LeadBundle\Segment\ContactSegmentFilterCrate;

class NotCompanyFilterException extends \RuntimeException
{
    public static function create(ContactSegmentFilterCrate $contactSegmentFilterCrate): self
    {
        $message = 'Filter '.$contactSegmentFilterCrate->getField().' not a company filter';

        return new self($message);
    }
}
