<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'description' => $this->faker->word,
            'address' => $this->faker->word,
        ];
    }
}
