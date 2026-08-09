<?php

namespace App\Http\Middleware;

use App\Services\Authorization\PermissionService;
use Closure;
use Illuminate\Http\Request;

class RequirePermission
{
    private PermissionService $permissions;

    public function __construct(PermissionService $permissions)
    {
        $this->permissions = $permissions;
    }

    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$this->permissions->userHasPermission($user, $permission)) {
            return response()->json([
                'message' => 'Forbidden.',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
