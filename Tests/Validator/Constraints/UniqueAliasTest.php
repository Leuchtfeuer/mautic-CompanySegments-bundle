<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Validator\Constraints;

use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Validator\Constraints\UniqueAlias;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Exception\MissingOptionsException;

class UniqueAliasTest extends TestCase
{
    public function testThrowsConstraintExceptionIfNoFieldIsSet(): void
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('The options "field" must be set for constraint');
        new UniqueAlias();
    }
}
