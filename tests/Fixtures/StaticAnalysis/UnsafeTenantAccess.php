<?php

namespace Tests\Fixtures\StaticAnalysis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UnsafeTenantModel extends Model {}

function queryWithoutTenantProtection(): void
{
    DB::table('users')->get();
    DB::table('invoice_items')->get();
    DB::table('journal_entry_lines')->get();
    UnsafeTenantModel::withoutGlobalScopes()->get();
}
