<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\SalesHistory;

class SalesHistoryFactory extends Factory
{
    protected $model = SalesHistory::class;

    public function definition()
    {
        return [
            'sale_id' => function () {
                // Aquí puedes usar un factory de Customer para obtener un sale_id válido
                return \App\Models\Sale::factory()->create()->id;
            },
            'customer_id' => function () {
                // Aquí puedes usar un factory de Customer para obtener un customer_id válido
                return \App\Models\Customer::factory()->create()->id;
            },
            'product_id' => function () {
                // Aquí puedes usar un factory de Customer para obtener un product_id válido
                return \App\Models\Product::factory()->create()->id;
            },
            'quantity' => $this->faker->randomNumber(2),
            'total_amount' => $this->faker->randomFloat(2, 10, 1000), // Genera un número decimal aleatorio entre 10 y 1000 con 2 decimales
        ];
    }
}
