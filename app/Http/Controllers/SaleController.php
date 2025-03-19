<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalesHistory;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::all();
        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:customers,client_id',
            'seller_id' => 'required|exists:users,id',
            'pop_id' => 'required|exists:point_of_sales,id',
            'total' => 'required|numeric',
            'carts' => 'required|array',
            'carts.*.productId' => 'required|exists:products,id',
            'carts.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // Crear la venta
            $sale = Sale::create([
                'customer_id' => $request->input('client_id'),
                'seller_id' => $request->input('seller_id'),
                'pop_id' => $request->input('pop_id'),
                'total' => $request->input('total'),
            ]);

            // Crear el historial de ventas
            foreach ($request->input('carts') as $cart) {
                SalesHistory::create([
                    'sale_id' => $sale->id,
                    'product_id' => $cart['productId'],
                    'quantity' => $cart['quantity'],
                ]);
            }

            // Actualizar el inventario
            foreach ($request->input('carts') as $cart) {
                $inventory = Inventory::where('product_id', $cart['productId'])->first();
                $inventory->quantity -= $cart['quantity'];
                $inventory->save();
            }


            DB::commit();

            return response()->json($sale, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $sale = Sale::find($id);

        if (!$sale) {
            return response()->json(['message' => 'Venta no encontrado'], 404);
        }

        return response()->json($sale, 200);
    }

    public function put(Request $request, $id)
    {
        $sale = Sale::find($id);

        if (!$sale) {
            return response()->json(['message' => 'Venta no encontrado'], 404);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'customer_id' => 'required|exists:customers,id',
            'quantity' => 'required|integer|min:0',
        ]);

        $sale->update([
            'quantity' => $request->input('quantity'),
        ]);

        return response()->json($sale, 200);
    }

    public function destroy($id)
    {
        $sale = Sale::find($id);

        if (!$sale) {
            return response()->json(['message' => 'Venta no encontrado'], 404);
        }

        $sale->delete();

        return response()->json(null, 204);
    }
}
