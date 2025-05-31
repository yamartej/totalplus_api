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
            'client_id' => 'required',
        ]);

        // Crear el nuevo cliente
        $customer = Customer::create([
            'client_id' => $request->input('client_id'),
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
        //$customer = Customer::find($id);
        $customer = Customer::where('client_id', $id)->get();

        // Si el cliente no existe, responder con el código de estado 404 (No encontrado)
        if (!$customer) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
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
            'client_id' => 'required',
        ]);

        // Actualizar los datos del cliente
        $customer->update([
            'client_id' => $request->input('client_id'),
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

    /**
     * Search customers by client_id.
     *
     * @param  int  $client_id
     * @return \Illuminate\Http\Response
     */
    public function searchByClientId($client_id)
    {
        // Buscar clientes por client_id en la base de datos
        $customers = Customer::where('client_id', $client_id)->get();

        // Si no se encuentran clientes, responder con el código de estado 404 (No encontrado)
        if ($customers->isEmpty()) {
            return response()->json(['message' => 'Clientes no encontrados'], 404);
        }

        // Responder con los clientes encontrados y el código de estado 200 (OK)
        return response()->json($customers, 200);
    }

    public function getCustomersWithCreditsAndPayments()
    {
        // Obtener todos los clientes con sus detalles de crédito y pagos
        $customers = Customer::with(['creditCustomerDetails', 'sale'])->get();

        // Responder con los clientes y el código de estado 200 (OK)
        return response()->json($customers, 200);
    }
}
