<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\SaleDetail;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class SaleDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);
        DB::beginTransaction();

        try {
            $sale = Sale::findOrFail($request->sale_id);

            $saleDetail = SaleDetail::findOrFail($id);

            // calcular el monto de los productos que tenía
            $product = Product::findOrFail($saleDetail->product->id ?? $saleDetail->product_id);

            if ($saleDetail->quantity >= 3) {
                $totalByProduct = $product->wholesale_final_cost * $saleDetail->quantity;
            } else {
                $totalByProduct = $product->final_cost * $saleDetail->quantity;
            }
            $total = $sale->total_amount - $totalByProduct;
            $newTotal = $total + $request->input('new_total_amount');
            // Actualizar el inventario
            $inventory = $product->inventory;
            if ($inventory) {
                $inventory->quantity += $saleDetail->quantity; // Devolver la cantidad anterior al inventario
                $inventory->quantity -= $request->input('quantity'); // Restar la nueva cantidad
                $inventory->save();
            } else {
                return response()->json(['error' => 'Inventario no encontrado para el producto'], 404);
            }

            $saleDetail->update([
                'quantity' => $request->input('quantity'),
            ]);

            $sale->update([
                'total_amount' => $newTotal,
            ]);

            $response = [
                $saleDetail,
                $sale,
            ];

            DB::commit();

            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $saleDetail = SaleDetail::find($id);

        $product = Product::findOrFail($saleDetail->product->id ?? $saleDetail->product_id);
        $inventory = $product->inventory;
        $inventory->quantity += $saleDetail->quantity; // Devolver la cantidad anterior al inventario
        $inventory->save();

        $sale = Sale::findOrFail($saleDetail->sale_id);
        if ($sale->details->count() <= 1) {
            $sale->delete();
        } else {
            if ($saleDetail->quantity >= 3) {
                $totalByProduct = $product->wholesale_final_cost * $saleDetail->quantity;
            } else {
                $totalByProduct = $product->final_cost * $saleDetail->quantity;
            }
            $total = $sale->total_amount - $totalByProduct;
            $sale->update([
                'total_amount' => $total,
            ]);
        }


        if (!$saleDetail) {
            return response()->json(['message' => 'Venta no encontrado'], 404);
        }

        $saleDetail->delete();

        return response()->json(null, 204);
    }
}
