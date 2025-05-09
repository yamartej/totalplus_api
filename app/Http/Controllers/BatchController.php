<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Batch;
use Illuminate\Support\Facades\DB;

class BatchController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $batches = Batch::all();
        return response()->json($batches);
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1',
            'status' => 'required|in:created,received', // Validar que el status sea válido
            'order_creation_date' => 'required|date',
        ]);

        // Verificar si ya existe un lote para el producto y el almacén especificados
        $batch = Batch::where('name', $request->input('name'))
            ->first();
        if ($batch) {
            // Si ya existe, devolver un mensaje de error
            return response()->json(['message' => 'Ya existe un lote con este nombre'], 400);
        } else {
            // Crear el nuevo lote
            $batch = Batch::create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'quantity' => $request->input('quantity'),
                'status' => $request->input('status'), // Guardar el campo status
                'order_creation_date' => $request->input('order_creation_date'),
            ]);
        }

        // Responder con el lote creado y el código de estado 201 (Recurso creado)  
        return response()->json($batch, 201);
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
        $batch = Batch::find($id);

        if (!$batch) {
            return response()->json(['message' => 'Lote no encontrado'], 404);
        }

        // Validar los datos recibidos
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1',
            'status' => 'required|in:created,received', // Validar que el status sea válido
            'order_creation_date' => 'required|date',
        ]);

        // Actualizar los datos del lote
        $batch->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'quantity' => $request->input('quantity'),
            'status' => $request->input('status'), // Actualizar el campo status
            'order_creation_date' => $request->input('order_creation_date'),
        ]);

        return response()->json($batch, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $batch = Batch::find($id);

        if (!$batch) {
            return response()->json(['message' => 'Lote no encontrado'], 404);
        }

        $batch->delete();

        return response()->json(['message' => 'Lote eliminado correctamente'], 200);
    }

    public function getBatchesReceived()
    {
        $batches = DB::table('batches')
            ->where('status', '=', 'received')
            ->get();
        return response()->json($batches);
    }
}
