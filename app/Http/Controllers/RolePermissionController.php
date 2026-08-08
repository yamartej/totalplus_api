<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RolePermission;
use App\Models\Roles;
use App\Models\Menu;

class RolePermissionController extends Controller
{
    public function index()
    {
        $roles = Roles::all();
        $menus = Menu::all();
        $permissions = RolePermission::all();

        return response()->json([
            'roles' => $roles,
            'menus' => $menus,
            'permissions' => $permissions,
        ]);
    }

    public function update(Request $request)
    {
        $permissions = $request->all(); // Obtener todos los datos enviados en el request
        foreach ($permissions as $permission) {
            RolePermission::updateOrCreate(
                ['role_id' => $permission['role_id'], 'menu_id' => $permission['menu_id']],
                ['can_access' => $permission['can_access']]
            );
        }

        return response()->json(['message' => 'Permisos actualizados correctamente']);
    }
}
