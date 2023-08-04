<?php

// app/Http/Controllers/InventoryController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Product;

class InventoryController extends Controller
{
    public function index()
    {
        $inventories = Inventory::all();
        return response()->json($inventories);
    }

    public function create(Request $request)
    {
        // Validar los datos recibidos del inventario
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:0',
        ]);

        // Crear el nuevo inventario
        $inventory = Inventory::create([
            'product_id' => $request->input('product_id'),
            'quantity' => $request->input('quantity'),
        ]);

        // Responder con el inventario creado y el código de estado 201 (Recurso creado)
        return response()->json($inventory, 201);
    }

    public function get($id)
    {
        // Buscar el producto por su ID en la base de datos
        $inventory = Inventory::find($id);

        // Si el producto no existe, responder con el código de estado 404 (No encontrado)
        if (!$inventory) {
            return response()->json(['message' => 'Inventario no encontrado'], 404);
        }

        // Responder con el producto y el código de estado 200 (OK)
        return response()->json($inventory, 200);
    }

    public function update(Request $request, $id)
    {
        // Buscar el inventario por su ID en la base de datos
        $inventory = Inventory::find($id);

        // Si el inventario no existe, responder con el código de estado 404 (No encontrado)
        if (!$inventory) {
            return response()->json(['message' => 'Inventario no encontrado'], 404);
        }

        // Validar los datos recibidos para actualizar el inventario
        $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        // Actualizar los datos del inventario
        $inventory->update([
            'quantity' => $request->input('quantity'),
        ]);

        // Responder con el inventario actualizado y el código de estado 200 (OK)
        return response()->json($inventory, 200);
    }

    public function delete($id)
    {
        // Buscar el inventario por su ID en la base de datos
        $inventory = Inventory::find($id);

        // Si el inventario no existe, responder con el código de estado 404 (No encontrado)
        if (!$inventory) {
            return response()->json(['message' => 'Inventario no encontrado'], 404);
        }

        // Eliminar el inventario de la base de datos
        $inventory->delete();

        // Responder con el código de estado 204 (Sin contenido) ya que no hay respuesta para eliminar
        return response()->json(null, 204);
    }
}
