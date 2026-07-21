<?php

namespace App\Infrastructure\Shared;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasTenantScope
{
    public static function bootHasTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            if (app()->bound('current_tenant')) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('tenant_id'),
                    current_tenant_id(),
                );
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') === null && app()->bound('current_tenant')) {
                $model->setAttribute('tenant_id', current_tenant_id());
            }
        });
    }
}
