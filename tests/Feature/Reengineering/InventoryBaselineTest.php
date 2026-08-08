<?php

namespace Tests\Feature\Reengineering;

use App\Models\Company;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryBaselineTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer ' . $user->createToken('phase-0-test')->plainTextToken,
        ];
    }

    private function companyAndWarehouses(): array
    {
        $company = Company::create([
            'name' => 'Inventory Baseline Company',
        ]);

        $warehouseA = Warehouse::create([
            'name' => 'Almacén A',
            'description' => 'A',
            'address' => 'A',
            'company_id' => $company->id,
        ]);

        $warehouseB = Warehouse::create([
            'name' => 'Almacén B',
            'description' => 'B',
            'address' => 'B',
            'company_id' => $company->id,
        ]);

        return compact('company', 'warehouseA', 'warehouseB');
    }

    public function test_current_inventory_update_replaces_balance_directly(): void
    {
        $user = User::factory()->create();
        $ctx = $this->companyAndWarehouses();
        $product = Product::factory()->create();

        $inventory = Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->putJson('/api/inventory/' . $inventory->id, [
                'quantity' => 3,
            ]);

        $response->assertStatus(200);
        $this->assertSame(3, (int) $inventory->fresh()->quantity);
    }

    /**
     * Documenta un defecto actual:
     * saveInventoryProducts recibe warehouse_id pero, para productos ya
     * existentes, busca únicamente por product_id y actualiza el primer saldo.
     *
     * @group baseline-defect
     */
    public function test_current_inventory_addition_is_not_scoped_to_requested_warehouse(): void
    {
        $user = User::factory()->create();
        $ctx = $this->companyAndWarehouses();
        $product = Product::factory()->create();

        $inventoryA = Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $ctx['warehouseA']->id,
            'quantity' => 10,
        ]);

        $inventoryB = Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $ctx['warehouseB']->id,
            'quantity' => 20,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->postJson('/api/inventory/saveInventoryProducts', [
                'warehouse_id' => $ctx['warehouseB']->id,
                'new_product_ids' => [],
                'product_ids' => [
                    ['id' => $product->id, 'quantity' => 5],
                ],
            ]);

        $response->assertStatus(201);

        // Caracterización del comportamiento actual:
        // se actualiza el primer Inventory encontrado por product_id.
        $this->assertSame(15, (int) $inventoryA->fresh()->quantity);
        $this->assertSame(20, (int) $inventoryB->fresh()->quantity);
    }
}
