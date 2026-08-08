<?php

namespace App\Http\Controllers;

use App\Http\Resources\RolesResource;
use App\Models\Roles;
use Illuminate\Http\Request;

class RolesController extends Controller
{
    public function index()
    {
        $roles = Roles::all();

        //return RolesResource::collection($roles);
        return  response()->json($roles);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $roles = Roles::create($data);

        return new RolesResource($roles);
    }

    public function show(Roles $roles)
    {
        return new RolesResource($roles);
    }

    public function put(Request $request, Roles $roles)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $roles->update($data);

        return new RolesResource($roles);
    }

    public function destroy($id)
    {
        $roles = Roles::find($id);

        if (!$roles) {
            return response()->json(['message' => 'Rol no encontrado'], 404);
        }

        $roles->delete();

        return response()->json(null, 204);
    }
}
