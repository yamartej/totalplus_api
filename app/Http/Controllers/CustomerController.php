<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $customers = Customer::all();
        return response()->json($customers);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validar los datos recibidos del cliente
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required',
        ]);

        // Crear el nuevo cliente
        $customer = Customer::create([
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'phone' => $request->input('phone'),            
        ]);

        // Responder con el cliente creado y el código de estado 201 (Recurso creado)
        return response()->json($customer, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // Buscar el cliente por su ID en la base de datos
        $customer = Customer::find($id);

        // Si el cliente no existe, responder con el código de estado 404 (No encontrado)
        if (!$customer) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        // Responder con el cliente y el código de estado 200 (OK)
        return response()->json($customer, 200);
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
        // Buscar el cliente por su ID en la base de datos
        $customer = Customer::find($id);

        // Si el cliente no existe, responder con el código de estado 404 (No encontrado)
        if (!$customer) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        // Validar los datos recibidos para actualizar el cliente
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required',
        ]);

        // Actualizar los datos del cliente
        $customer = Customer::create([
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'phone' => $request->input('phone'),            
        ]);

        // Responder con el cliente actualizado y el código de estado 200 (OK)
        return response()->json($customer, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete($id)
    {
        // Buscar el cliente por su ID en la base de datos
        $customer = Customer::find($id);

        // Si el cliente no existe, responder con el código de estado 404 (No encontrado)
        if (!$customer) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        // Eliminar el cliente de la base de datos
        $customer->delete();

        // Responder con el código de estado 204 (Sin contenido) ya que no hay respuesta para eliminar
        return response()->json(null, 204);
        
    }
}
