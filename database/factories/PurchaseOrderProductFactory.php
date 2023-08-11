<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\PurchaseOrderProduct;

class PurchaseOrderProductFactory extends Factory
{
    protected $model = PurchaseOrderProduct::class;

    public function definition()
    {
        return [
            'purchase_order_id' => function () {
                 return \App\Models\PurchaseOrder::factory()->create()->id;
            },
            'product_id' => function () {
                return \App\Models\Product::factory()->create()->id;
           },
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'quantity' => $this->faker->randomNumber(2),            
        ];
    }
}
