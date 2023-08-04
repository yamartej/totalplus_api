<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Sale;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition()
    {
        return [
            'customer_id' => function () {
                // Aquí puedes usar un factory de Customer para obtener un customer_id válido
                return \App\Models\Customer::factory()->create()->id;
            },
            'quantity' => $this->faker->randomNumber(2),
        ];
    }
}
