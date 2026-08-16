<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    private function requestedCompanyId(Request $request)
    {
        if ($request->has('company_id')) {
            return $request->input('company_id');
        }

        if ($request->has('companyName')) {
            return $request->input('companyName');
        }

        return null;
    }

    public function index(Request $request, TenantContext $tenant)
    {
        $users = $tenant
            ->scope(
                User::query()->with(['roles', 'company']),
                $request->user(),
                $request->query('company_id')
            )
            ->get();

        return response()->json($users);
    }

    public function store(Request $request, TenantContext $tenant)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'rol' => 'required',
        ]);

        $companyId = $tenant->resolveCompanyId(
            $request->user(),
            $this->requestedCompanyId($request),
            true
        );

        if (User::where('email', $request->input('email'))->exists()) {
            return response()->json([
                'message' => 'El correo ya está registrado',
            ], 409);
        }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'company_id' => $companyId,
        ]);

        $user->roles()->sync((array) $request->input('rol'));

        return response()->json(
            $user->load(['roles', 'company']),
            201
        );
    }

    public function getUsersByRole(
        Request $request,
        TenantContext $tenant
    ) {
        $roleIds = [8, 9, 10, 16];

        $query = User::whereHas(
            'roles',
            function ($query) use ($roleIds) {
                $query->whereIn('roles.id', $roleIds);
            }
        );

        $users = $tenant
            ->scope(
                $query,
                $request->user(),
                $request->query('company_id')
            )
            ->get();

        if ($users->isEmpty()) {
            return response()->json([
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        return response()->json($users, 200);
    }

    public function show(
        $id,
        Request $request,
        TenantContext $tenant
    ) {
        $user = $tenant
            ->scope(
                User::query()->with('roles')->where('id', $id),
                $request->user(),
                $request->query('company_id')
            )
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        return response()->json($user, 200);
    }

    public function update(
        Request $request,
        $id,
        TenantContext $tenant
    ) {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $tenant
            ->scope(
                User::query()->with('roles')->where('id', $id),
                $request->user(),
                $this->requestedCompanyId($request)
            )
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        $updates = [
            'name' => $request->input('name'),
        ];

        if ($request->filled('email')) {
            $duplicate = User::where(
                'email',
                $request->input('email')
            )
                ->where('id', '!=', $user->id)
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'message' => 'El correo ya está registrado',
                ], 409);
            }

            $updates['email'] = $request->input('email');
        }

        $user->update($updates);

        if ($request->has('rol')) {
            $user->roles()->sync(
                (array) $request->input('rol')
            );
        }

        return response()->json(
            $user->fresh()->load(['roles', 'company']),
            200
        );
    }

    public function destroy(
        $id,
        Request $request,
        TenantContext $tenant
    ) {
        $user = $tenant
            ->scope(
                User::query()->with('roles')->where('id', $id),
                $request->user(),
                $this->requestedCompanyId($request)
            )
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        if ((int) $user->id === (int) $request->user()->id) {
            return response()->json([
                'message' => 'No puede eliminar su propio usuario',
            ], 400);
        }

        $hasDependencies = DB::table('point_of_sales')
            ->where('seller_id', $user->id)
            ->exists();

        if ($hasDependencies) {
            return response()->json([
                'message' =>
                    'No se puede eliminar el usuario porque tiene dependencias en Punto de Ventas',
            ], 400);
        }

        $user->delete();

        return response()->json(null, 204);
    }

    public function getUsersByCompany(
        $companyId,
        Request $request,
        TenantContext $tenant
    ) {
        $resolvedCompanyId = $tenant->resolveCompanyId(
            $request->user(),
            $companyId
        );

        $users = User::where(
            'company_id',
            $resolvedCompanyId
        )
            ->with(['roles', 'company'])
            ->get();

        if ($users->isEmpty()) {
            return response()->json([
                'message' =>
                    'No se encontraron usuarios para la empresa especificada',
            ], 404);
        }

        return response()->json($users, 200);
    }
}
