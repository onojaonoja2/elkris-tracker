<?php

namespace Tests\Feature\Filament;

use App\Enums\OrderStatus;
use App\Filament\Widgets\LeadOrderAssignmentWidget;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadOrderAssignmentWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_sees_pending_unassigned_orders_from_team(): void
    {
        $lead = User::factory()->lead()->create();
        $rep = User::factory()->rep()->create(['lead_id' => $lead->id]);
        $this->actingAs($lead);

        $leadPending = Order::factory()->create([
            'user_id' => $lead->id,
            'status' => 'pending',
            'assigned_to' => null,
        ]);

        $repPending = Order::factory()->create([
            'user_id' => $rep->id,
            'status' => 'pending',
            'assigned_to' => null,
        ]);

        $assigned = Order::factory()->create([
            'user_id' => $lead->id,
            'status' => 'assigned',
            'assigned_to' => User::factory()->communitySalesRepresentative()->create()->id,
        ]);

        Livewire::test(LeadOrderAssignmentWidget::class)
            ->assertCanSeeTableRecords([$leadPending, $repPending])
            ->assertCanNotSeeTableRecords([$assigned]);
    }

    public function test_lead_cannot_see_orders_from_another_leads_team(): void
    {
        $lead = User::factory()->lead()->create();
        $otherLead = User::factory()->lead()->create();
        $otherRep = User::factory()->rep()->create(['lead_id' => $otherLead->id]);
        $this->actingAs($lead);

        $otherPending = Order::factory()->create([
            'user_id' => $otherLead->id,
            'status' => 'pending',
            'assigned_to' => null,
        ]);

        $otherRepPending = Order::factory()->create([
            'user_id' => $otherRep->id,
            'status' => 'pending',
            'assigned_to' => null,
        ]);

        Livewire::test(LeadOrderAssignmentWidget::class)
            ->assertCanNotSeeTableRecords([$otherPending, $otherRepPending]);
    }

    public function test_lead_can_assign_pending_order_to_csr(): void
    {
        $lead = User::factory()->lead()->create();
        $this->actingAs($lead);

        $csr = User::factory()->communitySalesRepresentative()->create();
        $order = Order::factory()->create([
            'user_id' => $lead->id,
            'status' => 'pending',
            'assigned_to' => null,
        ]);

        Livewire::test(LeadOrderAssignmentWidget::class)
            ->callAction(TestAction::make('assignToCsr')->table($order), [
                'csr_id' => $csr->id,
                'notes' => 'Deliver at 5pm',
            ]);

        $order->refresh();

        $this->assertSame($csr->id, $order->assigned_to);
        $this->assertSame(OrderStatus::Assigned, $order->status);
        $this->assertSame('Deliver at 5pm', $order->assignment_notes);
    }

    public function test_widget_is_hidden_for_non_leads(): void
    {
        $rep = User::factory()->rep()->create();
        $this->actingAs($rep);

        $this->assertFalse(LeadOrderAssignmentWidget::canView());
    }
}
