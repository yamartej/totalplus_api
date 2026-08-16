<?php

namespace App\Services\Authorization;

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Collection;

class RoleAccessService
{
    public function roleIds(User $user): Collection
    {
        return $user->roles()
            ->pluck('roles.id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    public function accessibleMenuIds(User $user): Collection
    {
        $roleIds = $this->roleIds($user);

        if ($roleIds->isEmpty()) {
            return collect();
        }

        return RolePermission::query()
            ->whereIn('role_id', $roleIds)
            ->where('can_access', true)
            ->pluck('menu_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public function canAccessMenu(User $user, int $menuId): bool
    {
        return $this->accessibleMenuIds($user)->contains($menuId);
    }
}