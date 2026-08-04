<?php

namespace Tests\Feature\Filament;

use App\Enums\OrderStatus;
use App\Filament\Widgets\LeadAssignedCsrOrdersWidget;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadAssignedCsrOrdersWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_sees_team_orders_assigned_to_csr(): void
    {
        $lead = User::factory()->lead()->create();
        $rep = User::factory()->rep()->create(['lead_id' => $lead->id]);
        $this->actingAs($lead);

        $csr = User::factory()->communitySalesRepresentative()->create();

        $leadAssigned = Order::factory()->create([
            'user_id' => $lead->id,
            'status' => 'assigned',
            'assigned_to' => $csr->id,
        ]);

        $repAssigned = Order::factory()->create([
            'user_id' => $rep->id,
            'status' => 'assigned',
            'assigned_to' => $csr->id,
        ]);

        Livewire::test(LeadAssignedCsrOrdersWidget::class)
            ->assertCanSeeTableRecords([$leadAssigned, $repAssigned]);
    }

    public function test_lead_does_not_see_delivered_or_unassigned_orders(): void
    {
        $lead = User::factory()->lead()->create();
        $this->actingAs($lead);

        $csr = User::factory()->communitySalesRepresentative()->create();

        $delivered = Order::factory()->create([
            'user_id' => $lead->id,
            'status' => 'delivered',
            'assigned_to' => $csr->id,
        ]);

        $unassigned = Order::factory()->create([
            'user_id' => $lead->id,
            'status' => 'pending',
            'assigned_to' => null,
        ]);

        Livewire::test(LeadAssignedCsrOrdersWidget::class)
            ->assertCanNotSeeTableRecords([$delivered, $unassigned]);
    }

    public function test_lead_does_not_see_another_leads_team_orders(): void
    {
        $lead = User::factory()->lead()->create();
        $otherLead = User::factory()->lead()->create();
        $this->actingAs($lead);

        $csr = User::factory()->communitySalesRepresentative()->create();

        $other = Order::factory()->create([
            'user_id' => $otherLead->id,
            'status' => 'assigned',
            'assigned_to' => $csr->id,
        ]);

        Livewire::test(LeadAssignedCsrOrdersWidget::class)
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_lead_can_unassign_order_from_csr(): void
    {
        $lead = User::factory()->lead()->create();
        $this->actingAs($lead);

        $csr = User::factory()->communitySalesRepresentative()->create();
        $order = Order::factory()->create([
            'user_id' => $lead->id,
            'status' => 'assigned',
            'assigned_to' => $csr->id,
        ]);

        Livewire::test(LeadAssignedCsrOrdersWidget::class)
            ->callAction(TestAction::make('unassign')->table($order));

        $order->refresh();

        $this->assertNull($order->assigned_to);
        $this->assertSame(OrderStatus::Pending, $order->status);
    }

    public function test_widget_is_hidden_for_non_leads(): void
    {
        $rep = User::factory()->rep()->create();
        $this->actingAs($rep);

        $this->assertFalse(LeadAssignedCsrOrdersWidget::canView());
    }
}
