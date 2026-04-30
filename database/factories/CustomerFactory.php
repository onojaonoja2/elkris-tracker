<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'customer_name' => $this->faker->name(),
            'phone_number' => $this->faker->numerify('###########'),
            'address' => $this->faker->address(),
            'city' => 'lagos_island',
            'state' => 'Lagos',
            'region' => 'South West',
            'priority' => 'medium',
            'customer_status' => 'customer',
            'rep_acceptance_status' => null,
        ];
    }
}
