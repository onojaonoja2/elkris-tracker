<?php

namespace Tests\Feature\Filament;

use App\Enums\OrderStatus;
use App\Filament\Widgets\RepOrderAssignmentWidget;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RepOrderAssignmentWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_rep_sees_only_their_own_pending_unassigned_orders(): void
    {
        $rep = User::factory()->rep()->create();
        $otherRep = User::factory()->rep()->create();
        $this->actingAs($rep);

        $own = Order::factory()->create([
            'user_id' => $rep->id,
            'status' => 'pending',
            'assigned_to' => null,
        ]);

        $peerPending = Order::factory()->create([
            'user_id' => $otherRep->id,
            'status' => 'pending',
            'assigned_to' => null,
        ]);

        $assigned = Order::factory()->create([
            'user_id' => $rep->id,
            'status' => 'assigned',
            'assigned_to' => User::factory()->communitySalesRepresentative()->create()->id,
        ]);

        Livewire::test(RepOrderAssignmentWidget::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$peerPending, $assigned]);
    }

    public function test_rep_can_assign_their_own_pending_order_to_csr(): void
    {
        $rep = User::factory()->rep()->create();
        $this->actingAs($rep);

        $csr = User::factory()->communitySalesRepresentative()->create();
        $order = Order::factory()->create([
            'user_id' => $rep->id,
            'status' => 'pending',
            'assigned_to' => null,
        ]);

        Livewire::test(RepOrderAssignmentWidget::class)
            ->callAction(TestAction::make('assignToCsr')->table($order), [
                'csr_id' => $csr->id,
                'notes' => 'Deliver at 3pm',
            ]);

        $order->refresh();

        $this->assertSame($csr->id, $order->assigned_to);
        $this->assertSame(OrderStatus::Assigned, $order->status);
        $this->assertSame('Deliver at 3pm', $order->assignment_notes);
    }

    public function test_widget_is_hidden_for_non_reps(): void
    {
        $lead = User::factory()->lead()->create();
        $this->actingAs($lead);

        $this->assertFalse(RepOrderAssignmentWidget::canView());
    }
}
