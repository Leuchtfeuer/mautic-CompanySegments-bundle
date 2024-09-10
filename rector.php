<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\FunctionLike\SimplifyUselessVariableRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\Class_\ReturnTypeFromStrictTernaryRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\ClassMethod\BoolReturnTypeFromBooleanConstReturnsRector;
use Rector\TypeDeclaration\Rector\ClassMethod\BoolReturnTypeFromBooleanStrictReturnsRector;
use Rector\TypeDeclaration\Rector\ClassMethod\NumericReturnTypeFromStrictScalarReturnsRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromReturnDirectArrayRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromReturnNewRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictConstantReturnRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNativeCallRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNewArrayRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictParamRector;
use Rector\TypeDeclaration\Rector\ClassMethod\StringReturnTypeFromStrictScalarReturnsRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictSetUpRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__,
    ]);

    $rectorConfig->skip([
        '*/Tests',
        '*/vendor',
    ]);

    // Define what rule sets will be applied
    $rectorConfig->sets([
        SetList::DEAD_CODE,
        SetList::PHP_80,
        SetList::TYPE_DECLARATION,
    ]);

    // Define what single rules will be applied
    $rectorConfig->rules([
        Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictTypedCallRector::class,

        Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector::class,
        NumericReturnTypeFromStrictScalarReturnsRector::class,
        ReturnTypeFromReturnNewRector::class,
        ReturnTypeFromStrictNativeCallRector::class,
        ReturnTypeFromStrictNewArrayRector::class,
        ReturnTypeFromStrictParamRector::class,
        ReturnTypeFromStrictTernaryRector::class,
        ClassPropertyAssignToConstructorPromotionRector::class,
        BoolReturnTypeFromBooleanConstReturnsRector::class,
        StringReturnTypeFromStrictScalarReturnsRector::class,
        AddVoidReturnTypeWhereNoReturnRector::class,
        TypedPropertyFromStrictConstructorRector::class,
        TypedPropertyFromStrictSetUpRector::class,
        RemoveUnusedVariableAssignRector::class,
        RemoveUselessVarTagRector::class,
        SimplifyUselessVariableRector::class,
        BoolReturnTypeFromBooleanStrictReturnsRector::class,
        ReturnTypeFromStrictConstantReturnRector::class,
        ReturnTypeFromReturnDirectArrayRector::class,
    ]);
};
