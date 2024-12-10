<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/check-email',
        'api/register',
        'api/permissions',
        'api/menu_items',
        'api/users',
        'api/users/*',
        'api/categories',
        'api/categories/*',
        'api/products',
        'api/products/*',
        'api/customers',
        'api/customers/*',

    ];
}
