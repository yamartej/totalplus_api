<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatedCompany;
use Tests\TestCase;

/**
 * Current sales endpoint contract. Product/stock/valid sale creation are
 * covered separately by SaleBaselineTest and SaleTransactionStorePhase3Test.
 */
class SaleTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticatedCompany;

    private function sale(): Sale
    {
        $customer = Customer::create([
            'company_id' => $this->qaCompany->id,
            'client_id' => 700001,
            'name' => 'QA customer', 'address' => 'QA',
            'phone' => '123456',
        ]);

        return Sale::create([
            'company_id' => $this->qaCompany->id,
            'customer_id' => $customer->id,
            'seller_id' => $this->qaAdmin->id,
            'total_amount' => 123.50,
            'type_of_sale' => 'normal',
        ]);
    }

    public function test_store_sale_rejects_incomplete_payload(): void
    {
        $this->postJson('/api/sales', ['total' => 100])
            ->assertStatus(422);
        $this->assertSame(0, Sale::count());
    }

    public function test_show_sale(): void
    {
        $sale = $this->sale();
        $this->getJson('/api/sales/' . $sale->id)
            ->assertOk()
            ->assertJsonPath('id', $sale->id);
    }

    public function test_put_sale_rejects_client_total_override(): void
    {
        $sale = $this->sale();
        $this->putJson('/api/sales/' . $sale->id, ['total_amount' => 0])
            ->assertStatus(422);
        $this->assertSame(123.5, (float) $sale->fresh()->total_amount);
    }

    public function test_destroy_sale_preserves_audit_history(): void
    {
        $sale = $this->sale();
        $this->deleteJson('/api/sales/' . $sale->id)->assertNoContent();
        $this->assertNotNull($sale->fresh());
        $this->assertNotNull($sale->fresh()->voided_at);
        $this->assertSame(123.5, (float) $sale->fresh()->total_amount);
        $this->deleteJson('/api/sales/' . $sale->id)->assertStatus(422);
    }

    public function test_example(): void
    {
        $this->get('/')->assertOk();
    }
}
