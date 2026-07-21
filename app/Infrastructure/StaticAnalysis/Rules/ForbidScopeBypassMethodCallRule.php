<?php

namespace App\Infrastructure\StaticAnalysis\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<MethodCall> */
class ForbidScopeBypassMethodCallRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier
            || ! in_array($node->name->toString(), ['withoutGlobalScopes', 'withoutTenantScope'], true)
            || str_contains(str_replace('\\', '/', $scope->getFile()), '/app/Console/Commands/')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                "{$node->name->toString()}() is forbidden outside approved Artisan commands.",
            )->identifier('inventorySaas.scopeBypass')->build(),
        ];
    }
}
