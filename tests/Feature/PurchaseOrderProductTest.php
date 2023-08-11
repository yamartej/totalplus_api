<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\PurchaseOrderProduct;
use App\Models\PurchaseOrder;
use App\Models\Product;
use Illuminate\Http\Response;

class PurchaseOrderProductTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_all_purchase_order_product()
    {
        PurchaseOrderProduct::factory()->count(3)->create();

        $response = $this->get('/api/purchaseorderproducts');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            '*' => [
                'id',
                'purchase_order_id',
                'product_id',
                'price',
                'quantity',
                'created_at',
                'updated_at',
            ],
        ]);

        // Verificar la cantidad de registros en la respuesta
        $response->assertJsonCount(3);

    }

    public function test_create_purchase_order_product()
    {
        $purchase_order = PurchaseOrder::factory()->create();
        $product = Product::factory()->create();

        $purchase_order_product = [
            'purchase_order_id' => $purchase_order->id,
            'product_id' => $product->id,
            'price' => 10, 
            'quantity' => 10, 
        ];

        $response = $this->post('/api/purchaseorderproducts', $purchase_order_product);

        $response->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('purchase_order_products', $purchase_order_product);
    }

    public function test_show_purchase_order()
    {
        $purchase_order_product = PurchaseOrderProduct::factory()->create();

        $response = $this->get('/api/purchaseorderproducts/' . $purchase_order_product->id);

        $response->assertStatus(200);
 
         // Verificar que los datos del cliente obtenido coinciden con los datos del cliente de prueba
         $response->assertJson([
             'id' => $purchase_order_product->id,
             'product_id' => $purchase_order_product->product_id,
             'price' => $purchase_order_product->price,
             'quantity' => $purchase_order_product->quantity,
         ]);
    }

    public function test_put_purchase_order_product()
    {
        
        $purchase_order_product = PurchaseOrderProduct::factory()->create();

        $purchase_order_product_data = [
            'price' => 10, 
            'quantity' => 10, 
        ];

        $response = $this->putJson('/api/purchaseorderproducts/' . $purchase_order_product->id, $purchase_order_product_data);
        
        $response->assertStatus(200);
 
        $response->assertJson([
            'price' => $purchase_order_product_data['price'],
            'quantity' => $purchase_order_product_data['quantity'],
        ]);
        
    }

    public function test_delete_purchase_order_product()
    {
        $purchase_order_product = PurchaseOrderProduct::factory()->create();
        
        $response = $this->deleteJson('/api/purchaseorderproducts/'. $purchase_order_product->id);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('purchase_order_products', ['id' => $purchase_order_product->id]);
    }

    public function test_example()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
