<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;


class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = User::with(['roles', 'company'])->get();
        return response()->json($users);
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
            'email' => 'required|email'
        ]);

        // Buscar o crear la empresa
        if ($request->input('company')) {
            $company = Company::create(['name' => $request->input('company')]);
            $company_id = $company->id;
        } else {
            $company_id = $request->input('companyName');
        }


        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'company_id' => $company_id,
        ]);

        $user->roles()->attach($request->input('rol'));

        return response()->json($user, 201);
    }

    public function getUsersByRole()
    {
        $roleIds = [8, 9, 10, 16]; // IDs de roles predefinidos

        $users = User::whereHas('roles', function ($query) use ($roleIds) {
            $query->whereIn('roles.id', $roleIds); // Asegúrate de usar la columna correcta
        })->get();

        if ($users->isEmpty()) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        return response()->json($users, 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::with('roles')->find($id);
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        // Responder con el producto y el código de estado 200 (OK)
        return response()->json($user, 200);
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
        $user = User::with('roles')->find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user->update([
            'name' => $request->input('name'),
            //'company_id' => $company_id,
            'email' => $request->input('email'),
            'rol_id' => $request->input('rol')
        ]);

        $user->roles()->sync($request->input('rol'));

        // Responder con el inventario actualizado y el código de estado 200 (OK)
        return response()->json($user, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = User::with('roles')->find($id);
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        // Verificar si el usuario tiene dependencias en point_of_sales
        $hasDependencies = DB::table('point_of_sales')->where('seller_id', $id)->exists();
        if ($hasDependencies) {
            return response()->json(['message' => 'No se puede eliminar el usuario porque tiene dependencias en Punto de Ventas'], 400);
        }

        // Eliminar el usuario
        $user->delete();

        return response()->json(null, 204);
    }

    public function getUsersByCompany($companyId)
    {
        $users = User::where('company_id', $companyId)->with(['roles', 'company'])->get();
        if ($users->isEmpty()) {
            return response()->json(['message' => 'No se encontraron usuarios para la empresa especificada'], 404);
        }

        return response()->json($users, 200);
    }
}
