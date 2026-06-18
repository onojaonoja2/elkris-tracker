<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\TrialOrderStatus;
use App\Events\CustomerAssigned;
use App\Events\OrderCreated;
use App\Events\TrialOrderApproved;
use App\Models\AgentStock;
use App\Models\Customer;
use App\Models\Order;
use App\Models\TrialOrder;
use App\Models\User;
use App\Services\TrialOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_creation_fires_event(): void
    {
        Event::fake([CustomerAssigned::class]);

        $lead = User::factory()->lead()->create();

        $customer = Customer::create([
            'customer_name' => 'Test Customer',
            'phone_number' => '12345678901',
            'address' => '123 Test Street',
            'city' => 'lagos_island',
            'state' => 'Lagos',
            'region' => 'South West',
            'priority' => 'medium',
            'customer_status' => 'customer',
            'lead_id' => $lead->id,
        ]);

        Event::assertDispatched(CustomerAssigned::class, function ($event) use ($customer) {
            return $event->customer->id === $customer->id;
        });
    }

    public function test_order_creation_fires_event(): void
    {
        Event::fake([OrderCreated::class]);

        $customer = Customer::factory()->create();
        $user = User::factory()->rep()->create();

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'total_price' => 10000,
        ]);

        Event::assertDispatched(OrderCreated::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });
    }

    public function test_trial_order_approval_fires_event(): void
    {
        $agent = User::factory()->fieldAgent()->create();
        $accountant = User::factory()->accountant()->create();

        AgentStock::create([
            'user_id' => $agent->id,
            'product_name' => 'Ora herbal mix',
            'grammage' => 100,
            'quantity' => 50,
        ]);

        $trialOrder = TrialOrder::create([
            'agent_id' => $agent->id,
            'products' => [
                [
                    'product_name' => 'Ora herbal mix',
                    'grammage' => 100,
                    'quantity' => 5,
                ],
            ],
            'total_value' => 5000,
            'status' => TrialOrderStatus::ReceiptUploaded,
            'payment_status' => PaymentStatus::Pending,
        ]);

        Event::fake([TrialOrderApproved::class]);

        $this->actingAs($accountant);

        $service = new TrialOrderService;
        $service->approveByAccountant($trialOrder, 'Approved');

        Event::assertDispatched(TrialOrderApproved::class, function ($event) use ($trialOrder) {
            return $event->trialOrder->id === $trialOrder->id;
        });
    }
}
