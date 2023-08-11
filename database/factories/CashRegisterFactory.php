<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashRegisterFactory extends Factory
{
    protected $model = CashRegister::class;
    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'description' => $this->faker->word,
            'warehouse_id' => function () {
                return Warehouse::factory()->create()->id;
            },
        ];
    }
}
