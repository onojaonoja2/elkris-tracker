<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'lga_id' => null,
            'status' => 'pending',
            'total_price' => 0,
            'expected_delivery_date' => null,
        ];
    }
}
