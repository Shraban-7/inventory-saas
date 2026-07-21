<?php

namespace App\Infrastructure\StaticAnalysis\Rules;

use App\Infrastructure\Models\Permission;
use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<InClassNode> */
class RequireTenantScopeRule implements Rule
{
    /** @var list<class-string<Model>> */
    private const GLOBAL_MODELS = [
        Tenant::class,
        Permission::class,
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        $className = $class->getName();

        if (! $class->isSubclassOf(Model::class)
            || $class->getNativeReflection()->isAbstract()
            || in_array($className, self::GLOBAL_MODELS, true)
            || in_array(HasTenantScope::class, $class->getNativeReflection()->getTraitNames(), true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                "{$className} must use HasTenantScope because it is a tenant-owned Eloquent model.",
            )->identifier('inventorySaas.missingTenantScope')->build(),
        ];
    }
}
