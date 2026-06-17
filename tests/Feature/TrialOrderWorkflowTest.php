<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\TrialOrderStatus;
use App\Models\AgentStock;
use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\TrialOrder;
use App\Models\User;
use App\Notifications\NewSubmissionNotification;
use App\Services\TrialOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TrialOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_order_can_be_created_by_field_agent(): void
    {
        $agent = User::factory()->fieldAgent()->create();
        $stockist = Stockist::factory()->create();

        $this->actingAs($agent);

        $trialOrder = TrialOrder::create([
            'agent_id' => $agent->id,
            'stockist_id' => $stockist->id,
            'products' => [
                [
                    'product_name' => 'Ora herbal mix',
                    'grammage' => '100g',
                    'quantity' => 5,
                ],
            ],
            'total_value' => 5000,
            'status' => TrialOrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $this->assertDatabaseHas('trial_orders', [
            'id' => $trialOrder->id,
            'agent_id' => $agent->id,
            'status' => TrialOrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
        ]);
    }

    public function test_trial_order_admin_notification_on_creation(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $agent = User::factory()->fieldAgent()->create();
        $stockist = Stockist::factory()->create();

        $this->actingAs($agent);

        TrialOrder::create([
            'agent_id' => $agent->id,
            'stockist_id' => $stockist->id,
            'products' => [
                [
                    'product_name' => 'Ora herbal mix',
                    'grammage' => '100g',
                    'quantity' => 5,
                ],
            ],
            'total_value' => 5000,
            'status' => TrialOrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
        ]);

        Notification::assertSentTo(
            $admin,
            NewSubmissionNotification::class
        );
    }

    public function test_accountant_can_approve_trial_order(): void
    {
        $agent = User::factory()->fieldAgent()->create();
        $accountant = User::factory()->accountant()->create();
        $stockist = Stockist::factory()->create();

        StockistStock::create([
            'stockist_id' => $stockist->id,
            'product_name' => 'Ora herbal mix',
            'grammage' => 100,
            'quantity' => 50,
        ]);

        AgentStock::create([
            'user_id' => $agent->id,
            'product_name' => 'Ora herbal mix',
            'grammage' => 100,
            'quantity' => 50,
        ]);

        $trialOrder = TrialOrder::create([
            'agent_id' => $agent->id,
            'stockist_id' => $stockist->id,
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

        $this->actingAs($accountant);

        $service = new TrialOrderService;
        $service->approveByAccountant($trialOrder, 'Verified and approved');

        $trialOrder->refresh();

        $this->assertEquals(TrialOrderStatus::Approved, $trialOrder->status);
        $this->assertEquals(PaymentStatus::Completed, $trialOrder->payment_status);
        $this->assertEquals($accountant->id, $trialOrder->accountant_verified_by);
    }

    public function test_accountant_approval_deducts_stock(): void
    {
        $agent = User::factory()->fieldAgent()->create();
        $accountant = User::factory()->accountant()->create();
        $stockist = Stockist::factory()->create();

        StockistStock::create([
            'stockist_id' => $stockist->id,
            'product_name' => 'Ora herbal mix',
            'grammage' => 100,
            'quantity' => 50,
        ]);

        AgentStock::create([
            'user_id' => $agent->id,
            'product_name' => 'Ora herbal mix',
            'grammage' => 100,
            'quantity' => 50,
        ]);

        $trialOrder = TrialOrder::create([
            'agent_id' => $agent->id,
            'stockist_id' => $stockist->id,
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

        $this->actingAs($accountant);

        $service = new TrialOrderService;
        $service->approveByAccountant($trialOrder, 'Approved');

        $stock = StockistStock::where('stockist_id', $stockist->id)
            ->where('product_name', 'Ora herbal mix')
            ->where('grammage', 100)
            ->first();

        $this->assertEquals(45, $stock->quantity);
    }

    public function test_accountant_can_reject_trial_order(): void
    {
        $agent = User::factory()->fieldAgent()->create();
        $accountant = User::factory()->accountant()->create();
        $stockist = Stockist::factory()->create();

        $trialOrder = TrialOrder::create([
            'agent_id' => $agent->id,
            'stockist_id' => $stockist->id,
            'products' => [
                [
                    'product_name' => 'Ora herbal mix',
                    'grammage' => '100g',
                    'quantity' => 5,
                ],
            ],
            'total_value' => 5000,
            'status' => TrialOrderStatus::ReceiptUploaded,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($accountant);

        $service = new TrialOrderService;
        $service->rejectByAccountant($trialOrder, 'Receipt does not match');

        $trialOrder->refresh();

        $this->assertEquals(TrialOrderStatus::Rejected, $trialOrder->status);
        $this->assertEquals('Receipt does not match', $trialOrder->accountant_notes);
    }

    public function test_locked_trial_order_cannot_be_edited(): void
    {
        $trialOrder = TrialOrder::create([
            'agent_id' => User::factory()->fieldAgent()->create()->id,
            'stockist_id' => Stockist::factory()->create()->id,
            'products' => [
                [
                    'product_name' => 'Ora herbal mix',
                    'grammage' => '100g',
                    'quantity' => 5,
                ],
            ],
            'total_value' => 5000,
            'status' => TrialOrderStatus::Approved,
            'payment_status' => PaymentStatus::Completed,
        ]);

        $this->assertTrue($trialOrder->isLocked());
    }

    public function test_trial_order_approved_notifies_agent(): void
    {
        Notification::fake();

        $agent = User::factory()->fieldAgent()->create();
        $accountant = User::factory()->accountant()->create();
        $stockist = Stockist::factory()->create();

        StockistStock::create([
            'stockist_id' => $stockist->id,
            'product_name' => 'Ora herbal mix',
            'grammage' => 100,
            'quantity' => 50,
        ]);

        AgentStock::create([
            'user_id' => $agent->id,
            'product_name' => 'Ora herbal mix',
            'grammage' => 100,
            'quantity' => 50,
        ]);

        $trialOrder = TrialOrder::create([
            'agent_id' => $agent->id,
            'stockist_id' => $stockist->id,
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

        $this->actingAs($accountant);

        $service = new TrialOrderService;
        $service->approveByAccountant($trialOrder, 'Approved');

        Notification::assertSentTo(
            $agent,
            NewSubmissionNotification::class
        );
    }
}
