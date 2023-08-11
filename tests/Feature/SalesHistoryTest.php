<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Sale;

class SalesHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_all_sales()
    {
        Sale::factory()->count(3)->create();

        $response = $this->get('/api/sales');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            '*' => [
                'id',
                'customer_id',
                'product_id',
                'quantity',
                'created_at',
                'updated_at',
            ],
        ]);

        // Verificar la cantidad de registros en la respuesta
        $response->assertJsonCount(3);
    }

    public function test_example()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
