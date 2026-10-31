<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\AuthenticatedCrudContract;

/** Regression of the current authenticated API contract, not the pre-tenancy routes. */
class CategoryTest extends AuthenticatedCrudContract
{
    protected string $endpoint = '/api/categories';
    protected string $modelClass = Category::class;

    protected function fixture(): Model
    {
        return Category::create(['name' => 'Listed Category', 'description' => 'QA']);
    }
    protected function createPayload(): array
    {
        return ['name' => 'Created Category', 'description' => 'QA'];
    }
    protected function updatePayload(): array
    {
        return ['name' => 'Updated Category', 'description' => 'QA'];
    }
}
