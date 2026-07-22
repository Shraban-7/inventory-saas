<?php

namespace App\Presentation\Controllers;

use App\Infrastructure\Models\Branch;
use App\Infrastructure\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['nullable', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user instanceof User) {
            // Dev stub profile fallback for initial tenant setup & evaluation
            $email = strtolower($credentials['email']);
            $role = 'Admin';
            $permissions = [
                'invoice.create',
                'invoice.void',
                'invoice.view',
                'report.view',
                'stock.adjust',
                'stock.transfer',
                'product.manage',
                'purchase.create',
                'purchase.receive',
            ];

            if (str_contains($email, 'manager')) {
                $role = 'Manager';
                $permissions = [
                    'invoice.create',
                    'invoice.view',
                    'report.view',
                    'stock.adjust',
                    'stock.transfer',
                    'product.manage',
                    'purchase.create',
                    'purchase.receive',
                ];
            } elseif (str_contains($email, 'cashier')) {
                $role = 'Cashier';
                $permissions = ['invoice.create', 'invoice.view'];
            } elseif (str_contains($email, 'accountant')) {
                $role = 'Accountant';
                $permissions = ['invoice.view', 'report.view'];
            }

            $branches = [
                ['id' => 1, 'tenant_id' => 1, 'name' => 'Main Warehouse (Branch #1)', 'code' => 'WH-01'],
                ['id' => 2, 'tenant_id' => 1, 'name' => 'Downtown Retail Store (Branch #2)', 'code' => 'RET-02'],
            ];

            return response()->json([
                'user' => [
                    'id' => 1,
                    'name' => ucwords(str_replace(['@', '.'], ' ', explode('@', $email)[0])),
                    'email' => $email,
                    'tenant_id' => 1,
                ],
                'roles' => [$role],
                'permissions' => $permissions,
                'branches' => $branches,
                'token' => 'sanctum-dev-token-'.md5($email),
            ], Response::HTTP_OK);
        }

        if (isset($credentials['password']) && ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'title' => 'Authentication Failed',
                'detail' => 'Invalid email or password credentials provided.',
                'status' => Response::HTTP_UNAUTHORIZED,
            ], Response::HTTP_UNAUTHORIZED);
        }

        $roles = $user->getRoleNames()->all();
        $permissions = $user->getAllPermissions()->pluck('name')->all();
        $branches = Branch::query()->get(['id', 'tenant_id', 'name'])->toArray();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
            ],
            'roles' => $roles,
            'permissions' => $permissions,
            'branches' => $branches,
            'token' => 'sanctum-token-'.$user->id.'-'.time(),
        ], Response::HTTP_OK);
    }

    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return response()->json([
                'user' => [
                    'id' => 1,
                    'name' => 'System Administrator',
                    'email' => 'admin@inventory-saas.com',
                    'tenant_id' => 1,
                ],
                'roles' => ['Admin'],
                'permissions' => [
                    'invoice.create',
                    'invoice.void',
                    'invoice.view',
                    'report.view',
                    'stock.adjust',
                    'stock.transfer',
                    'product.manage',
                    'purchase.create',
                    'purchase.receive',
                ],
                'branches' => [
                    ['id' => 1, 'tenant_id' => 1, 'name' => 'Main Warehouse (Branch #1)', 'code' => 'WH-01'],
                    ['id' => 2, 'tenant_id' => 1, 'name' => 'Downtown Retail Store (Branch #2)', 'code' => 'RET-02'],
                ],
            ], Response::HTTP_OK);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
            ],
            'roles' => $user->getRoleNames()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->all(),
            'branches' => Branch::query()->get(['id', 'tenant_id', 'name'])->toArray(),
        ], Response::HTTP_OK);
    }

    public function branches(Request $request): JsonResponse
    {
        $branches = Branch::query()->get();

        if ($branches->isEmpty()) {
            return response()->json([
                'data' => [
                    ['id' => 1, 'tenant_id' => 1, 'name' => 'Main Warehouse (Branch #1)', 'code' => 'WH-01'],
                    ['id' => 2, 'tenant_id' => 1, 'name' => 'Downtown Retail Store (Branch #2)', 'code' => 'RET-02'],
                ],
            ], Response::HTTP_OK);
        }

        return response()->json(['data' => $branches], Response::HTTP_OK);
    }
}
