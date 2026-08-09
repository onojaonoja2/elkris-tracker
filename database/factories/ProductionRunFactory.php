<?php

namespace Database\Factories;

use App\Models\ProductionRun;
use App\Models\RawMaterial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionRun>
 */
class ProductionRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'output_name' => $this->faker->words(2, true),
            'output_quantity' => $this->faker->randomFloat(4, 5, 50),
            'output_unit' => 'units',
            'status' => 'pending_review',
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ProductionRun $run) {
            if ($run->rawMaterials()->count() === 0) {
                $material = RawMaterial::factory()->create([
                    'quantity' => 1000.0000,
                ]);
                $quantityUsed = $this->faker->randomFloat(4, 10, 100);
                $run->rawMaterials()->attach($material->id, ['quantity_used' => $quantityUsed]);
                $material->decrement('quantity', $quantityUsed);
            }
        });
    }

    public function reviewed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reviewed',
            'accountant_reviewed_by' => User::factory(),
            'accountant_reviewed_at' => now(),
        ]);
    }

    public function flagged(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'flagged',
            'accountant_reviewed_by' => User::factory(),
            'accountant_reviewed_at' => now(),
        ]);
    }
}
