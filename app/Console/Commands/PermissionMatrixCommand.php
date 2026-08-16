<?php

namespace App\Console\Commands;

use App\Models\Menu;
use App\Models\RolePermission;
use App\Models\Roles;
use Illuminate\Console\Command;

class PermissionMatrixCommand extends Command
{
    protected $signature = 'security:permission-matrix';

    protected $description = 'Display the current menu/role authorization matrix';

    public function handle()
    {
        $roles = Roles::query()->orderBy('id')->get(['id', 'name']);
        $menus = Menu::query()
            ->orderBy('order')
            ->orderBy('id')
            ->get(['id', 'name', 'url', 'parent_id', 'order']);

        $permissions = RolePermission::query()
            ->where('can_access', true)
            ->get(['role_id', 'menu_id'])
            ->groupBy('menu_id');

        if ($menus->isEmpty()) {
            $this->warn('No menu records found.');
            return 0;
        }

        $rows = $menus->map(function ($menu) use ($permissions, $roles) {
            $roleIds = $permissions
                ->get($menu->id, collect())
                ->pluck('role_id')
                ->map(fn ($id) => (int) $id);

            $allowedRoles = $roles
                ->whereIn('id', $roleIds)
                ->pluck('name')
                ->implode(', ');

            return [
                $menu->id,
                $menu->name,
                $menu->url,
                $menu->parent_id,
                $allowedRoles ?: '(none)',
            ];
        })->all();

        $this->table(
            ['Menu ID', 'Menu', 'URL', 'Parent', 'Allowed roles'],
            $rows
        );

        $this->newLine();
        $this->info('Use this matrix to map API modules in Phase 1C-2.');

        return 0;
    }
}