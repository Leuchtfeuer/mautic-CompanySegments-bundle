<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Segment\Query;

class QueryException extends \Doctrine\DBAL\Exception
{
    /**
     * @param array<string> $registeredAliases
     */
    public static function unknownAlias(string $alias, array $registeredAliases): self
    {
        return new self("The given alias '".$alias."' is not part of ".
            'any FROM or JOIN clause table. The currently registered '.
            'aliases are: '.implode(', ', $registeredAliases).'.');
    }

    /**
     * @param array<string> $registeredAliases
     */
    public static function nonUniqueAlias(string $alias, array $registeredAliases): self
    {
        return new self("The given alias '".$alias."' is not unique ".
            'in FROM and JOIN clause table. The currently registered '.
            'aliases are: '.implode(', ', $registeredAliases).'.');
    }
}
