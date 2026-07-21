<?php

use Illuminate\Database\Eloquent\Model;

if (! function_exists('current_tenant_id')) {
    function current_tenant_id(): int
    {
        if (! app()->bound('current_tenant')) {
            throw new RuntimeException('No tenant is bound to the current application context.');
        }

        $tenant = app()->make('current_tenant');
        $tenantId = $tenant instanceof Model ? $tenant->getKey() : $tenant;

        if (! is_int($tenantId) && ! ctype_digit((string) $tenantId)) {
            throw new RuntimeException('The current tenant context has no valid identifier.');
        }

        return (int) $tenantId;
    }
}
