<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Sale;

class SaleTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_store_sale()
    {
        $customer = \App\Models\Customer::factory()->create();
        $product = \App\Models\Product::factory()->create();

        $data = [
            'product_id' => $product->id, 
            'customer_id' => $customer->id, 
            'quantity' => $this->faker->numberBetween($min = 1, $max = 10),
        ];

        $response = $this->postJson('/api/sales', $data);

        $response->assertStatus(201)
            ->assertJson($data);
    }

    public function test_show_sale()
    {
        $sale = \App\Models\Sale::factory()->create(['quantity' => 5]);
        //$customer = \App\Models\Customer::factory()->create();
        $product = \App\Models\Product::factory()->create();

        $response = $this->getJson('/api/sales/' . $sale->id);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $sale->id,
                'quantity' => 5,
            ]);
    }

    public function test_put_sale()
    {
        $customer = \App\Models\Customer::factory()->create();
        $sale = \App\Models\Sale::factory()->create();
        $product = \App\Models\Product::factory()->create();
        $quantity =  5;
        
        $data = [
            'product_id' => $product->id, 
            'customer_id' => $customer->id, 
            'quantity' => $quantity,
        ];

        $response = $this->putJson('/api/sales/' . $sale->id, $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $sale->id,
                'quantity' => $quantity,
            ]);

    }

    public function test_destroy_sale()
    {
        $sale = \App\Models\Sale::factory()->create();

        $response = $this->deleteJson('/api/sales/' . $sale->id);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);

    }
    
    public function test_example()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
