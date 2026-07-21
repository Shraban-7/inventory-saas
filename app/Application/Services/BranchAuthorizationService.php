<?php

namespace App\Application\Services;

use App\Infrastructure\Models\User;

class BranchAuthorizationService
{
    /** @param list<int> $branchIds */
    public function allows(User $user, string $permission, array $branchIds): bool
    {
        $authorizedBranchIds = $this->authorizedBranchIds($user, $permission);

        if ($authorizedBranchIds === null) {
            return true;
        }

        foreach ($branchIds as $branchId) {
            if (! in_array($branchId, $authorizedBranchIds, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<int>|null Null means all tenant branches are authorized. */
    public function authorizedBranchIds(User $user, string $permission): ?array
    {
        $branchIds = [];
        $roles = $user->roles()->with('permissions')->get();

        foreach ($roles as $role) {
            if (! $role->permissions->contains('name', $permission)) {
                continue;
            }

            $branchId = $role->pivot?->getAttribute('branch_id');

            if ($branchId === null) {
                return null;
            }

            $branchIds[] = (int) $branchId;
        }

        return array_values(array_unique($branchIds));
    }
}
