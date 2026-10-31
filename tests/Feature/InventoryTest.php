<?php

namespace Tests\Unit;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\AuthenticatedCrudContract;

/** Inventory API requires explicit company warehouse and valid product balance. */
class InventoryTest extends AuthenticatedCrudContract
{
    protected string $endpoint = '/api/inventory';
    protected string $modelClass = Inventory::class;

    private function warehouse(): Warehouse
    {
        return Warehouse::create([
            'name' => 'QA Warehouse '.uniqid(), 'address' => 'QA',
            'description' => 'QA', 'company_id' => $this->qaCompany->id,
        ]);
    }

    private function product(): Product
    {
        return Product::factory()->create(['company_id' => $this->qaCompany->id]);
    }

    protected function fixture(): Model
    {
        return Inventory::create([
            'product_id' => $this->product()->id,
            'warehouse_id' => $this->warehouse()->id,
            'quantity' => 5,
        ]);
    }

    protected function createPayload(): array
    {
        return [
            'product_ids' => [['id' => $this->product()->id, 'quantity' => 5]],
            'warehouse_id' => $this->warehouse()->id,
        ];
    }

    protected function updatePayload(): array
    {
        return ['quantity' => 8];
    }

    protected function idFromCreationResponse(array $json): int
    {
        return (int) $json['inventories'][0]['id'];
    }

    public function test_delete_record(): void
    {
        $record = $this->fixture();
        $this->deleteJson($this->endpoint . '/' . $record->id)
            ->assertNoContent();

        // Stock movement is retained: balance is deactivated at zero.
        $this->assertSame(0, (int) $record->fresh()->quantity);
    }
}
