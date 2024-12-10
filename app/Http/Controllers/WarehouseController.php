<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Warehouse;
use App\Http\Resources\WarehouseResource;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::all();
        //return WarehouseResource::collection($warehouses);
        return response()->json($warehouses);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
        ]);

        $warehouse = Warehouse::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'address' => $request->input('address'),
        ]);

        //return new WarehouseResource($warehouse);
        return response()->json($warehouse, 201);
    }

    public function show($id)
    {
        // Buscar el cliente por su ID en la base de datos
        $warehouse = Warehouse::find($id);

        // Si el almacen no existe, responder con el código de estado 404 (No encontrado)
        if (!$warehouse) {
            return response()->json(['message' => 'Alamacen no encontrado'], 404);
        }

        // Responder con el cliente y el código de estado 200 (OK)
        return response()->json($warehouse, 200);
    }

    public function put(Request $request, $id)
    {
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json(['message' => 'Alamacen no encontrado'], 404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
        ]);

        $warehouse->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'address' => $request->input('address'),
        ]);

        return response()->json($warehouse, 200);
    }

    public function destroy($id)
    {
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json(['message' => 'Alamacen no encontrado'], 404);
        }

        $warehouse->delete();

        return response()->json(null, 204);
    }
}
