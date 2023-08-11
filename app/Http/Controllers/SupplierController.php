<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Supplier;
use Illuminate\Http\Response;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::all();
        return response()->json($suppliers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $sales = Supplier::create([
            'name' => $request->input('name'),
            'phone' => $request->input('phone'),
        ]);

        return response()->json($sales, 201);
    }

    public function show($id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        return response()->json($supplier, 200);
    }

    public function put(Request $request, $id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $supplier->update([
            'name' => $request->input('name'),
            'phone' => $request->input('phone'),
        ]);

        return response()->json($supplier, 200);
    }

    public function destroy($id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $supplier->delete();

        return response()->json(null, 204);
    }
}
