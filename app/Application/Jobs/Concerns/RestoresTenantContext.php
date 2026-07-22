<?php

namespace App\Application\Jobs\Concerns;

use App\Infrastructure\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Context;

trait RestoresTenantContext
{
    private function withinTenant(callable $callback): mixed
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if (! $tenant instanceof Tenant) {
            throw (new ModelNotFoundException)->setModel(Tenant::class, [$this->tenantId]);
        }

        $resolvedPreviousTenant = app()->bound('current_tenant')
            ? app()->make('current_tenant')
            : null;
        $previousTenant = $resolvedPreviousTenant instanceof Tenant
            ? $resolvedPreviousTenant
            : null;
        $previousContextTenantId = Context::get('tenant_id');
        $connection = $tenant->getConnection();
        $usesMySqlSession = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true);
        $previousSessionTenantId = $usesMySqlSession
            ? $connection->scalar('SELECT @current_tenant_id')
            : null;

        app()->instance('current_tenant', $tenant);
        Context::add('tenant_id', $tenant->getKey());

        try {
            if ($usesMySqlSession) {
                $connection->statement('SET @current_tenant_id = ?', [$tenant->getKey()]);
            }

            return $callback();
        } finally {
            try {
                if ($usesMySqlSession) {
                    $connection->statement('SET @current_tenant_id = ?', [$previousSessionTenantId]);
                }
            } finally {
                if ($previousTenant !== null) {
                    app()->instance('current_tenant', $previousTenant);
                } else {
                    app()->forgetInstance('current_tenant');
                }

                if ($previousContextTenantId === null) {
                    Context::forget('tenant_id');
                } else {
                    Context::add('tenant_id', $previousContextTenantId);
                }
            }
        }
    }
}
