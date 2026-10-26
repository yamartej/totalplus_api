<?php

namespace Tests\Feature;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\AuthenticatedCrudContract;

/** Regression of the current authenticated API contract, not the pre-tenancy routes. */
class WarehouseTest extends AuthenticatedCrudContract
{
    protected string $endpoint = '/api/warehouses';
    protected string $modelClass = Warehouse::class;

    protected function fixture(): Model
    {
        return Warehouse::create([
            'name' => 'Listed Warehouse', 'description' => 'QA',
            'address' => 'Test Address', 'company_id' => $this->qaCompany->id,
        ]);
    }
    protected function createPayload(): array
    {
        return ['name' => 'Created Warehouse', 'address' => 'QA address', 'description' => 'QA'];
    }
    protected function updatePayload(): array
    {
        return ['name' => 'Updated Warehouse', 'address' => 'QA address', 'description' => 'QA'];
    }
}
