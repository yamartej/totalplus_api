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

    protected function expectedCreateStatus(): int
    {
        return 201; // JsonResource correctly reports a newly created model.
    }

    protected function idFromCreationResponse(array $json): int
    {
        return (int) $json['data']['id'];
    }

    public function test_list_records(): void
    {
        $record = $this->fixture();
        $response = $this->getJson($this->endpoint)->assertOk();
        $records = $response->json('data');
        $this->assertContains(
            (int) $record->id,
            array_map('intval', array_column($records, 'id'))
        );
    }

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
