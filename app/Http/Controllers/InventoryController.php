<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class InventoryController extends Controller
{
    public function index()
    {
        $inventories = Inventory::with(['product', 'warehouse'])->get();
        return response()->json($inventories);
    }

    public function create(Request $request)
    {
        // Validar los datos recibidos del inventario
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*.id' => 'required|exists:products,id',
            'product_ids.*.quantity' => 'required|integer|min:0',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        // Crear el nuevo inventario
        $inventories = [];
        foreach ($request->input('product_ids') as $product) {
            $inventories[] = Inventory::create([
                'product_id' => $product['id'],
                'warehouse_id' => $request->input('warehouse_id'),
                'quantity' => $product['quantity'], // Usar la cantidad proporcionada
            ]);
        }

        // Responder con el inventario creado y el código de estado 201 (Creado)
        return response()->json([
            'inventories' => $inventories,
        ], 201);
    }


    public function get($id)
    {
        // Buscar el producto por su ID en la base de datos
        $inventory = Inventory::with(['product', 'warehouse'])->find($id);

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

    public function saveInventoryProducts(Request $request)
    {
        try {
            DB::beginTransaction();
            //Agregar nuevos registros

            $inventoriesNew = [];
            if ($request->input('new_product_ids')) {
                foreach ($request->input('new_product_ids') as $product) {
                    $inventoriesNew[] = Inventory::create([
                        'product_id' => $product['id'],
                        'warehouse_id' => $request->input('warehouse_id'),
                        'quantity' => $product['quantity'], // Usar la cantidad proporcionada
                    ]);
                }
            }

            //Actualizar registros
            if ($request->input('product_ids')) {
                foreach ($request->input('product_ids') as $product) {
                    $inventory = Inventory::where('product_id', $product['id'])->first();
                    $currentQuantity = $inventory->quantity + $product['quantity'];
                    $inventory->update([
                        'quantity' => $currentQuantity,
                    ]);
                }
            }


            DB::commit();
            return response()->json(['message' => 'Inventario Actualizado'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            // Si llegamos a este punto, ocurrió un error
            return 'Transacción fallida: ' . $e->getMessage();
        }
    }
    public function removeAssignedInventory(Request $request)
    {
        try {
            DB::beginTransaction();

            if ($request->input('product_ids')) {
                foreach ($request->input('product_ids') as $product) {
                    $inventory = Inventory::where('product_id', $product['id'])->first();
                    $inventory->delete();
                }
            }

            DB::commit();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            // Si llegamos a este punto, ocurrió un error
            return 'Transacción fallida: ' . $e->getMessage();
        }
    }
}
