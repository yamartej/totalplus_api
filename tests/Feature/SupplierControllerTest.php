<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\AuthenticatedCrudContract;

/** Regression of the current authenticated API contract, not the pre-tenancy routes. */
class SupplierControllerTest extends AuthenticatedCrudContract
{
    protected string $endpoint = '/api/suppliers';
    protected string $modelClass = Supplier::class;

    private function supplier(): Supplier
    {
        $supplier = Supplier::create(['name' => 'QA Supplier '.uniqid(), 'phone' => '123456']);
        $supplier->company_id = $this->qaCompany->id;
        $supplier->save();
        return $supplier;
    }
    protected function fixture(): Model
    {
        return $this->supplier();
    }
    protected function createPayload(): array
    {
        return ['name' => 'Created Supplier', 'phone' => '123456789'];
    }
    protected function updatePayload(): array
    {
        return ['name' => 'Updated Supplier', 'phone' => '987654321'];
    }
}
