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

    public function show($id)
    {
        $record = Roles::findOrFail($id);
        return new RolesResource($record);
    }

    public function put(Request $request, $id)
    {
        $roles = Roles::findOrFail($id);
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
