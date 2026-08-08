<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        $finalCost = $this->faker->randomFloat(2, 10, 1000);

        return [
            'name' => $this->faker->word,
            'description' => $this->faker->paragraph,
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'image' => $this->faker->imageUrl(),
            'category_id' => function () {
                return \App\Models\Category::factory()->create()->id;
            },

            // Campos agregados posteriormente al esquema y hoy obligatorios.
            'final_cost' => $finalCost,
            'wholesale_final_cost' => max(0.01, round($finalCost * 0.90, 2)),
        ];
    }
}
