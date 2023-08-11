<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Http\Response;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_all_purchase_orders()
    {
        
        PurchaseOrder::factory()->count(3)->create();

        $response = $this->get('/api/purchaseorders');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            '*' => [
                'id',
                'supplier_id',
                'tracking_number',
                'shipping_cost',
                'created_at',
                'updated_at',
            ],
        ]);

        // Verificar la cantidad de registros en la respuesta
        $response->assertJsonCount(3);
        
    }

    public function test_create_purchase_order()
    {
        $supplier = Supplier::factory()->create();

        $purchase_order = [
            'supplier_id' => $supplier->id,
            'tracking_number' => 'aaaa123456', 
            'shipping_cost' => 10, 
        ];

        $response = $this->post('/api/purchaseorders', $purchase_order);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('purchase_orders', $purchase_order);

    }

    public function test_show_puchase_order()
    {
        $purchase_order = PurchaseOrder::factory()->create();

        $response = $this->get('/api/purchaseorders/' . $purchase_order->id);

        $response->assertStatus(200);
 
         // Verificar que los datos del cliente obtenido coinciden con los datos del cliente de prueba
         $response->assertJson([
             'id' => $purchase_order->id,
             'supplier_id' => $purchase_order->supplier_id,
             'tracking_number' => $purchase_order->tracking_number,
             'shipping_cost' => $purchase_order->shipping_cost,
         ]);
    }

    public function test_put_purchase_order()
    {
        $supplier = Supplier::factory()->create();

        $purchase_order = PurchaseOrder::factory()->create();

        $purchase_order_data = [
            'supplier_id' => $supplier->id,
            'tracking_number' => '0001',
            'shipping_cost' => 20,
        ];

        $response = $this->putJson('/api/purchaseorders/' . $purchase_order->id, $purchase_order_data);
        
        $response->assertStatus(200);
 
         $response->assertJson([
            'id' => $purchase_order->id,
            'tracking_number' => $purchase_order_data['tracking_number'],
            'shipping_cost' => $purchase_order_data['shipping_cost'],
         ]);

     
    }

    public function test_delete_purchase_order()
    {
        $purchase_order = PurchaseOrder::factory()->create();
        
        $response = $this->deleteJson('/api/purchaseorders/'. $purchase_order->id);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('purchase_orders', ['id' => $purchase_order->id]);
    }

    public function test_example()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
