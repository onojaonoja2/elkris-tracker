<?php

namespace Tests\Feature\Services;

use App\Models\AgentStock;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductType;
use App\Models\SalesRecord;
use App\Models\StockTransfer;
use App\Models\TrialOrder;
use App\Models\User;
use App\Notifications\NewSubmissionNotification;
use App\Services\UserDataSwapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserDataSwapServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rep_swap_moves_customers_pivots_orders_and_call_logs(): void
    {
        $repA = User::factory()->rep()->create(['name' => 'Rep A']);
        $repB = User::factory()->rep()->create(['name' => 'Rep B']);

        $customerA = Customer::factory()->repId($repA)->create(['rep_acceptance_status' => 'accepted']);
        $customerB = Customer::factory()->repId($repB)->create(['rep_acceptance_status' => 'pending']);

        $customerA->reps()->syncWithoutDetaching([$repA->id]);
        $customerB->reps()->syncWithoutDetaching([$repB->id]);

        $orderA = Order::factory()->create(['user_id' => $repA->id, 'customer_id' => $customerA->id]);
        $orderB = Order::factory()->create(['user_id' => $repB->id, 'customer_id' => $customerB->id]);

        CallLog::create([
            'user_id' => $repA->id,
            'customer_id' => $customerA->id,
            'called_at' => now(),
            'outcome' => 'connected',
        ]);

        $rejectedByA = Customer::factory()->repId($repB)->create(['rejected_by' => $repA->id]);

        UserDataSwapService::swap($repA, $repB);

        $this->assertSame($repB->id, $customerA->fresh()->rep_id);
        $this->assertSame($repA->id, $customerB->fresh()->rep_id);
        $this->assertSame('accepted', $customerA->fresh()->rep_acceptance_status);

        $this->assertTrue($customerA->reps()->where('users.id', $repB->id)->exists());
        $this->assertTrue($customerB->reps()->where('users.id', $repA->id)->exists());

        $this->assertSame($repB->id, $orderA->fresh()->user_id);
        $this->assertSame($repA->id, $orderB->fresh()->user_id);
        $this->assertSame($repB->id, CallLog::first()->user_id);

        $this->assertSame($repA->id, $rejectedByA->fresh()->rejected_by);

        $this->assertSame('Rep A', $repA->fresh()->name);
        $this->assertSame('Rep B', $repB->fresh()->name);
    }

    public function test_lead_swap_moves_customers_pivots_and_team_membership(): void
    {
        $leadA = User::factory()->lead()->create();
        $leadB = User::factory()->lead()->create();

        $customerA = Customer::factory()->leadId($leadA)->create();
        $customerB = Customer::factory()->leadId($leadB)->create();
        $customerA->leads()->syncWithoutDetaching([$leadA->id]);
        $customerB->leads()->syncWithoutDetaching([$leadB->id]);

        $repUnderA = User::factory()->rep()->create(['lead_id' => $leadA->id]);
        $repUnderB = User::factory()->rep()->create(['lead_id' => $leadB->id]);

        UserDataSwapService::swap($leadA, $leadB);

        $this->assertSame($leadB->id, $customerA->fresh()->lead_id);
        $this->assertSame($leadA->id, $customerB->fresh()->lead_id);
        $this->assertTrue($customerA->leads()->where('users.id', $leadB->id)->exists());
        $this->assertSame($leadB->id, $repUnderA->fresh()->lead_id);
        $this->assertSame($leadA->id, $repUnderB->fresh()->lead_id);
    }

    public function test_agent_swap_moves_customers_sales_trials_stock_and_transfers(): void
    {
        $agentA = User::factory()->communitySalesRepresentative()->create();
        $agentB = User::factory()->communitySalesRepresentative()->create();
        $productType = ProductType::factory()->create();

        $customerA = Customer::factory()->agentId($agentA)->create();
        $customerB = Customer::factory()->agentId($agentB)->create();

        SalesRecord::factory()->create(['agent_id' => $agentA->id]);
        SalesRecord::factory()->create(['agent_id' => $agentB->id]);
        TrialOrder::factory()->create(['agent_id' => $agentA->id]);
        TrialOrder::factory()->create(['agent_id' => $agentB->id]);
        AgentStock::factory()->create(['user_id' => $agentA->id, 'product_type_id' => $productType->id]);
        AgentStock::factory()->create(['user_id' => $agentB->id, 'product_type_id' => $productType->id]);
        StockTransfer::factory()->create(['to_agent_id' => $agentA->id]);
        StockTransfer::factory()->create(['to_agent_id' => $agentB->id]);

        UserDataSwapService::swap($agentA, $agentB);

        $this->assertSame($agentB->id, $customerA->fresh()->agent_id);
        $this->assertSame($agentA->id, $customerB->fresh()->agent_id);
        $this->assertSame([$agentB->id, $agentA->id], SalesRecord::orderBy('id')->pluck('agent_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([$agentB->id, $agentA->id], TrialOrder::orderBy('id')->pluck('agent_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([$agentB->id, $agentA->id], AgentStock::orderBy('id')->pluck('user_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([$agentB->id, $agentA->id], StockTransfer::orderBy('id')->pluck('to_agent_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_swap_preserves_uniqueness_when_customer_is_shared_by_both_reps(): void
    {
        $repA = User::factory()->rep()->create();
        $repB = User::factory()->rep()->create();

        $customer = Customer::factory()->repId($repA)->create();
        $customer->reps()->syncWithoutDetaching([$repA->id, $repB->id]);

        UserDataSwapService::swap($repA, $repB);

        $userIds = $customer->reps()->pluck('users.id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        $this->assertSame([$repA->id, $repB->id], $userIds);
        $this->assertSame($repB->id, $customer->fresh()->rep_id);
    }

    public function test_swap_rejects_mismatched_roles(): void
    {
        $rep = User::factory()->rep()->create();
        $lead = User::factory()->lead()->create();

        $this->expectException(ValidationException::class);

        UserDataSwapService::swap($rep, $lead);
    }

    public function test_swap_rejects_inactive_users(): void
    {
        $repA = User::factory()->rep()->create();
        $repB = User::factory()->rep()->create(['is_active' => false]);

        $this->expectException(ValidationException::class);

        UserDataSwapService::swap($repA, $repB);
    }

    public function test_swap_rejects_unsupported_roles(): void
    {
        $adminA = User::factory()->admin()->create();
        $adminB = User::factory()->admin()->create();

        $this->expectException(ValidationException::class);

        UserDataSwapService::swap($adminA, $adminB);
    }

    public function test_swap_rejects_same_user(): void
    {
        $rep = User::factory()->rep()->create();

        $this->expectException(ValidationException::class);

        UserDataSwapService::swap($rep, $rep);
    }

    public function test_swap_moves_notifications_between_users(): void
    {
        $repA = User::factory()->rep()->create();
        $repB = User::factory()->rep()->create();

        $repA->notify(new NewSubmissionNotification('trial_order', 'Trial', 'Body'));

        UserDataSwapService::swap($repA, $repB);

        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $repB->id)->count());
    }
}
