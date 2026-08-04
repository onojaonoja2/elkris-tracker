<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\OverdueCreditSalesWidget;
use App\Models\SalesRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OverdueCreditSalesWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_sees_their_own_overdue_credit_sales(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();

        $overdue = SalesRecord::factory()->overdue()->create([
            'agent_id' => $agent->id,
            'customer_name' => 'Overdue Customer',
            'total_value' => 5000.00,
        ]);

        SalesRecord::factory()->credit()->create([
            'agent_id' => $agent->id,
            'customer_name' => 'Not Due Yet',
            'total_value' => 3000.00,
        ]);

        SalesRecord::factory()->overdue()->create([
            'agent_id' => User::factory()->communitySalesRepresentative()->create()->id,
            'customer_name' => 'Other Agent Overdue',
            'total_value' => 9000.00,
        ]);

        $this->actingAs($agent);

        Livewire::test(OverdueCreditSalesWidget::class)
            ->assertCanSeeTableRecords([$overdue])
            ->assertSee('Overdue Customer')
            ->assertDontSee('Other Agent Overdue');
    }

    public function test_widget_is_hidden_for_non_agents(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $this->assertFalse(OverdueCreditSalesWidget::canView());
    }

    public function test_non_overdue_credit_sales_are_not_listed(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();

        $future = SalesRecord::factory()->credit()->create([
            'agent_id' => $agent->id,
            'expected_collection_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($agent);

        Livewire::test(OverdueCreditSalesWidget::class)
            ->assertCanNotSeeTableRecords([$future]);
    }
}
