<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\PurchaseOrder;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition()
    {
        return [
            'supplier_id' => function () {
                // Aquí puedes usar un factory de Proveedor para obtener un supplier_id válido
                return \App\Models\Supplier::factory()->create()->id;
            },
            'tracking_number' => $this->faker->word,
            'shipping_cost' => $this->faker->randomFloat(2, 10, 1000), // Genera un número decimal aleatorio entre 10 y 1000 con 2 decimales
        ];
    }
}
