<?php

namespace App\Services\Authorization;

use App\Models\User;

class PermissionService
{
    public function userHasPermission(User $user, string $permission): bool
    {
        return $user->roles()
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('permissions.name', $permission);
            })
            ->exists();
    }
}
