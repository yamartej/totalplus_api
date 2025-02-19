<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PointOfSale;

class PointOfSaleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $pop = PointOfSale::all();
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
        $pop = PointOfSale::where('identifier', $request->input('identifier'))->first();
        if ($pop) {
            return response()->json(['message' => 'El identificador ya existe'], 400);
        }

        $pop = PointOfSale::create([
            'identifier' => $request->input('identifier'),
            'ubication' => $request->input('ubication'),
        ]);

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

        $pop->update([
            'identifier' => $request->input('identifier'),
            'ubication' => $request->input('ubication'),
            'status' => $request->input('status'),
            'seller' => $request->input('seller'),
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
}
