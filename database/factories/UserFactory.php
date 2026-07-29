<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin->value,
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Manager->value,
        ]);
    }

    public function supervisor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Supervisor->value,
        ]);
    }

    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Lead->value,
        ]);
    }

    public function rep(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Rep->value,
        ]);
    }

    public function fieldAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::FieldAgent->value,
        ]);
    }

    public function communitySalesRepresentative(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::CommunitySalesRepresentative->value,
        ]);
    }

    public function accountant(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Accountant->value,
        ]);
    }

    public function sales(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Sales->value,
        ]);
    }

    public function productionManagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::ProductionManagement->value,
        ]);
    }
}
