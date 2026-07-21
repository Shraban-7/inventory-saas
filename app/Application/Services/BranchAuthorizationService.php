<?php

namespace App\Application\Services;

use App\Infrastructure\Models\User;

class BranchAuthorizationService
{
    /** @param list<int> $branchIds */
    public function allows(User $user, string $permission, array $branchIds): bool
    {
        $roles = $user->roles()->with('permissions')->get();

        foreach ($branchIds as $branchId) {
            $allowed = $roles->contains(function ($role) use ($branchId, $permission): bool {
                $roleBranchId = $role->pivot?->getAttribute('branch_id');

                return ($roleBranchId === null || (int) $roleBranchId === $branchId)
                    && $role->permissions->contains('name', $permission);
            });

            if (! $allowed) {
                return false;
            }
        }

        return true;
    }
}
