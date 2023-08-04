<?php

namespace Database\Factories;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition()
    {
        return [
            'product_id' => function () {
                // Aquí puedes usar un factory de Product para obtener un product_id válido
                return \App\Models\Product::factory()->create()->id;
            },
            'quantity' => $this->faker->randomNumber(2),
        ];
    }
}
