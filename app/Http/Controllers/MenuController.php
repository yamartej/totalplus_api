<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Services\Authorization\RoleAccessService;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index()
    {
        return response()->json(Menu::all());
    }

    public function getMenuByRoles(
        Request $request,
        RoleAccessService $roleAccess
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $menuIds = $roleAccess->accessibleMenuIds($user);

        if ($menuIds->isEmpty()) {
            return response()->json([]);
        }

        $menus = Menu::query()
            ->orderBy('order')
            ->orderBy('id')
            ->get();
        $byId = $menus->keyBy('id');
        $visibleIds = [];

        // A parent is a navigation container, not an additional API grant.
        // Include ancestors of accessible leaves without exposing siblings.
        foreach ($menuIds as $menuId) {
            $current = $byId->get((int) $menuId);
            $seen = [];

            while ($current && !isset($seen[$current->id])) {
                $seen[$current->id] = true;
                $visibleIds[$current->id] = true;
                $current = $current->parent_id === null
                    ? null
                    : $byId->get((int) $current->parent_id);
            }
        }

        $visible = $menus->filter(function (Menu $menu) use (
            $menus, $visibleIds
        ): bool {
            if (!isset($visibleIds[$menu->id])) {
                return false;
            }

            // Hide empty headings left from legacy navigation structures.
            if (
                $menu->parent_id === null
                && str_starts_with($menu->url, '/menu/')
            ) {
                return $menus->contains(fn (Menu $child) =>
                    (int) $child->parent_id === (int) $menu->id
                    && isset($visibleIds[$child->id])
                );
            }

            return true;
        })->values();

        return response()->json($visible);
    }
}