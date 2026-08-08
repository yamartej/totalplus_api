<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PointOfSale;
use App\Models\User;

class PointOfSaleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $pop = PointOfSale::with(['company'])->get();
        return response()->json($pop, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:255',
            'ubication' => 'required|string|max:255',
        ]);

        //validar que el identificador no exista
        $pop = PointOfSale::where('identifier', $request->input('identifier'))
            ->where('company_id', $request->input('company_id'))
            ->first();
        if ($pop) {
            return response()->json(['message' => 'El identificador ya existe'], 400);
        }

        $pop = PointOfSale::create([
            'identifier' => $request->input('identifier'),
            'ubication' => $request->input('ubication'),
            'company_id' => $request->input('company_id'),
        ]);
        $pop->load('company');


        return response()->json($pop, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $pop = PointOfSale::find($id);

        if (!$pop) {
            return response()->json(['message' => 'Punto de venta no encontrado'], 404);
        }

        return response()->json($pop, 200);
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

        $pop = PointOfSale::find($id);

        if (!$pop) {
            return response()->json(['message' => 'Punto de venta no encontrado'], 404);
        }
        $request->validate([
            'identifier' => 'required|string|max:255',
            'ubication' => 'required|string|max:255',
        ]);

        if ($request->input('seller_id')) {
            $user = User::find($request->input('seller_id'));
            if (!$user) {
                return response()->json(['message' => 'Vendedor no encontrado'], 404);
            }
            $seller = $user->name;
            $seller_id = $request->input('seller_id');
        } else {
            $seller = null;
            $seller_id = null;
        }

        $pop->update([
            'identifier' => $request->input('identifier'),
            'ubication' => $request->input('ubication'),
            'status' => $request->input('status'),
            'seller_id' => $seller_id,
            'seller' => $seller,
        ]);

        return response()->json($pop, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $pop = PointOfSale::find($id);

        if (!$pop) {
            return response()->json(['message' => 'Punto de venta no encontrado'], 404);
        }

        $pop->delete();

        return response()->json(['message' => 'Punto de venta eliminado'], 200);
    }

    public function getBySellerId($id)
    {
        $pop = PointOfSale::where('seller_id', $id)->get();

        if (!$pop) {
            return response()->json(['message' => 'Punto de venta no encontrado'], 404);
        }

        return response()->json($pop, 200);
    }

    public function getByCompanyId($id)
    {
        $pop = PointOfSale::with(['company'])->where('company_id', $id)->get();

        if ($pop->isEmpty()) {
            return response()->json(['message' => 'No se encontraron puntos de venta para esta empresa'], 404);
        }

        return response()->json($pop, 200);
    }
}
