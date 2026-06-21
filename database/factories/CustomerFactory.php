<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'customer_name' => fake()->name(),
            'phone_number' => fake()->numerify('###########'),
            'address' => fake()->address(),
            'city' => 'lagos_island',
            'state' => 'Lagos',
            'region' => 'South West',
            'priority' => 'medium',
            'customer_status' => 'customer',
            'rep_acceptance_status' => null,
        ];
    }

    public function agentId(User $agent): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_id' => $agent->id,
        ]);
    }

    public function leadId(User $lead): static
    {
        return $this->state(fn (array $attributes) => [
            'lead_id' => $lead->id,
        ]);
    }

    public function repId(User $rep): static
    {
        return $this->state(fn (array $attributes) => [
            'rep_id' => $rep->id,
        ]);
    }

    public function withState(int $stateId, int $lgaId, int $cityId): static
    {
        return $this->state(fn (array $attributes) => [
            'state_id' => $stateId,
            'lga_id' => $lgaId,
            'city_id' => $cityId,
        ]);
    }
}
