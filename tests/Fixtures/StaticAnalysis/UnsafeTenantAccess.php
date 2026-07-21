<?php

namespace Tests\Fixtures\StaticAnalysis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UnsafeTenantModel extends Model {}

function queryWithoutTenantProtection(): void
{
    DB::table('users')->get();
    UnsafeTenantModel::withoutGlobalScopes()->get();
}
