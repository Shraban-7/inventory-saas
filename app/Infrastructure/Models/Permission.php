<?php

namespace App\Infrastructure\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'guard_name',
    ];
}
