<?php

namespace App\Infrastructure\StaticAnalysis\Rules;

use Illuminate\Support\Facades\DB;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<StaticCall> */
class ForbidTenantTableQueryBuilderRule implements Rule
{
    /** @var list<string> */
    private const TENANT_TABLES = [
        'users',
        'branches',
        'roles',
        'role_user',
        'idempotency_requests',
        'categories',
        'products',
        'attributes',
        'product_variants',
        'warehouses',
        'stock_levels',
        'stock_movements',
        'inventory_lots',
        'stock_transfers',
        'customers',
        'invoices',
        'receipts',
        'credit_notes',
        'suppliers',
        'purchase_orders',
        'goods_receipt_notes',
        'bills',
        'bill_payments',
        'chart_of_accounts',
        'taxes',
        'journal_entries',
        'audit_logs',
    ];

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->class instanceof Node\Name
            || $scope->resolveName($node->class) !== DB::class
            || ! $node->name instanceof Identifier
            || $node->name->toString() !== 'table'
            || ! isset($node->args[0])
            || ! $node->args[0] instanceof Arg
            || ! $node->args[0]->value instanceof String_
            || ! in_array($node->args[0]->value->value, self::TENANT_TABLES, true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                "DB::table('{$node->args[0]->value->value}') bypasses mandatory tenant scoping.",
            )->identifier('inventorySaas.tenantTableQuery')->build(),
        ];
    }
}
