<?php

// app/Http/Controllers/MenuController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Menu;
use App\Models\RolePermission;

class MenuController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $menu = Menu::all();
        return response()->json($menu);
    }

    /**
     * Get the menu items accessible by the user's roles.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getMenuByRoles(Request $request)
    {
        $roleIds = $request->input('role_ids'); // Asumiendo que los roles se pasan como un array de IDs

        if (is_null($roleIds) || !is_array($roleIds) || empty($roleIds)) {
            return response()->json(['error' => 'Invalid role_ids parameter'], 400);
        }

        // Obtener los permisos de rol
        $permissions = RolePermission::whereIn('role_id', $roleIds)
            ->where('can_access', true)
            ->pluck('menu_id')
            ->unique();

        // Obtener los ítems del menú accesibles
        $menuItems = Menu::whereIn('id', $permissions)->get();

        return response()->json($menuItems);
    }

    // Otros métodos...
}
