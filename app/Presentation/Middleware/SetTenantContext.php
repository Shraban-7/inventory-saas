<?php

namespace App\Presentation\Middleware;

use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        $tenant = Tenant::query()->find($user->tenant_id);

        abort_unless($tenant instanceof Tenant, Response::HTTP_UNAUTHORIZED);

        app()->instance('current_tenant', $tenant);

        $usesMySqlSession = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);

        if ($usesMySqlSession) {
            DB::statement('SET @current_tenant_id = ?', [$tenant->getKey()]);
        }

        try {
            return $next($request);
        } finally {
            if ($usesMySqlSession) {
                DB::statement('SET @current_tenant_id = NULL');
            }

            app()->forgetInstance('current_tenant');
        }
    }
}
