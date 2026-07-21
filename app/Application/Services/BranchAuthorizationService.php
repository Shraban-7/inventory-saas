<?php

namespace App\Application\Services;

use App\Infrastructure\Models\User;

class BranchAuthorizationService
{
    public function allowsRoleOnBranch(User $user, string $roleName, int $branchId): bool
    {
        $roles = $user->roles()
            ->where('name', $roleName)
            ->get();

        foreach ($roles as $role) {
            $assignedBranchId = $role->pivot?->getAttribute('branch_id');

            if ($assignedBranchId === null || (int) $assignedBranchId === $branchId) {
                return true;
            }
        }

        return false;
    }

    public function hasTenantWideRole(User $user, string $roleName): bool
    {
        return $user->roles()
            ->where('name', $roleName)
            ->get()
            ->contains(
                static fn ($role): bool => $role->pivot?->getAttribute('branch_id') === null,
            );
    }

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
