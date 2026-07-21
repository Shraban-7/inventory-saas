<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Permission\Models\Role as SpatieRole;

/** @property-read Pivot|null $pivot */
class Role extends SpatieRole
{
    use HasTenantScope;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'name',
        'guard_name',
        'is_system',
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot('branch_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
