<?php

namespace Database\Factories;

use App\Models\CreditCollection;
use App\Models\SalesRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditCollection>
 */
class CreditCollectionFactory extends Factory
{
    protected $model = CreditCollection::class;

    public function definition(): array
    {
        return [
            'sales_record_id' => SalesRecord::factory(),
            'collected_amount' => fake()->randomFloat(2, 100, 10000),
            'collected_at' => now(),
            'collected_by' => User::factory(),
            'notes' => null,
        ];
    }
}
