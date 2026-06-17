<?php

namespace Database\Factories;

use App\Models\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductType>
 */
class ProductTypeFactory extends Factory
{
    protected $model = ProductType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Ora herbal mix', 'African bitters', 'Immune booster', 'Energy capsule']),
            'available_grammages' => [100, 200, 500],
            'is_active' => true,
        ];
    }
}
