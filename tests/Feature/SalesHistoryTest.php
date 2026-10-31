<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatedCompany;
use Tests\TestCase;

/** Verify sales history uses the current sale_id / details schema. */
class SalesHistoryTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticatedCompany;

    public function test_get_all_sales(): void
    {
        $customer = Customer::create([
            'company_id' => $this->qaCompany->id,
            'client_id' => 700002, 'name' => 'History customer',
            'address' => 'QA', 'phone' => '123456',
        ]);

        $sales = collect(range(1, 3))->map(function () use ($customer) {
            return Sale::create([
                'company_id' => $this->qaCompany->id,
                'customer_id' => $customer->id,
                'seller_id' => $this->qaAdmin->id,
                'total_amount' => 40,
                'type_of_sale' => 'normal',
            ]);
        });

        $response = $this->getJson('/api/sales')->assertOk()->assertJsonCount(3);
        $ids = array_column($response->json(), 'id');
        foreach ($sales as $sale) {
            $this->assertContains($sale->id, $ids);
        }
    }

    public function test_example(): void
    {
        $this->get('/')->assertOk();
    }
}
