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

        $menuItems = Menu::query()
            ->whereIn('id', $menuIds)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        return response()->json($menuItems);
    }
}