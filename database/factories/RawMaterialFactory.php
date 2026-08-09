<?php

namespace Database\Factories;

use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RawMaterial>
 */
class RawMaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'unit_of_measure' => $this->faker->randomElement(['kg', 'litres', 'units', 'bags']),
            'quantity' => $this->faker->randomFloat(4, 50, 1000),
            'reorder_level' => $this->faker->randomFloat(4, 10, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function lowStock(): self
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 5.0000,
            'reorder_level' => 100.0000,
        ]);
    }
}
