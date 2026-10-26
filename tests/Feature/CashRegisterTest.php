<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\AuthenticatedCrudContract;

/** Regression of the current authenticated API contract, not the pre-tenancy routes. */
class CashRegisterTest extends AuthenticatedCrudContract
{
    protected string $endpoint = '/api/cashregisters';
    protected string $modelClass = CashRegister::class;

    private function warehouse(): Warehouse
    {
        return Warehouse::create([
            'name' => 'Cash Warehouse '.uniqid(), 'address' => 'QA',
            'description' => 'QA', 'company_id' => $this->qaCompany->id,
        ]);
    }
    protected function fixture(): Model
    {
        return CashRegister::create([
            'name' => 'Register '.uniqid(), 'description' => 'QA',
            'warehouse_id' => $this->warehouse()->id,
        ]);
    }
    protected function createPayload(): array
    {
        return ['name' => 'Created Register', 'description' => 'QA',
            'warehouse_id' => $this->warehouse()->id];
    }
    protected function updatePayload(): array
    {
        return ['name' => 'Updated Register', 'description' => 'QA',
            'warehouse_id' => $this->warehouse()->id];
    }
}
