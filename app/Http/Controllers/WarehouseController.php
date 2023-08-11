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
        return WarehouseResource::collection($warehouses);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
        ]);

        $warehouse = Warehouse::create($data);

        return new WarehouseResource($warehouse);
    }

    public function show(Warehouse $warehouse)
    {
        return new WarehouseResource($warehouse);
    }

    public function put(Request $request, Warehouse $warehouse)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
        ]);

        $warehouse->update($data);

        return new WarehouseResource($warehouse);
    }

    public function destroy($id)
    {
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $warehouse->delete();

        return response()->json(null, 204);
    }
}
