<?php

namespace Tests\Feature\Widgets;

use App\Filament\Widgets\OfficeSalesStatsWidget;
use App\Models\SalesRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

class OfficeSalesStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_role_only_sees_own_office_sales(): void
    {
        $sales = User::factory()->sales()->create();
        $other = User::factory()->sales()->create();

        $this->actingAs($sales);

        $this->officeSale($sales, 'approved', 20000);
        $this->officeSale($sales, 'pending', 5000);
        $this->officeSale($other, 'approved', 90000);

        Livewire::test(OfficeSalesStatsWidget::class)
            ->assertSee('Office Sales')
            ->assertSee('₦25,000.00')
            ->assertSee('Approved: 1 | Pending approval: 1')
            ->assertDontSee('₦90,000.00');
    }

    public function test_manager_sees_all_office_sales(): void
    {
        $manager = User::factory()->manager()->create();
        $salesA = User::factory()->sales()->create();
        $salesB = User::factory()->sales()->create();

        $this->actingAs($manager);

        $this->officeSale($salesA, 'approved', 20000);
        $this->officeSale($salesB, 'pending', 90000);

        Livewire::test(OfficeSalesStatsWidget::class)
            ->assertSee('₦110,000.00')
            ->assertSee('Approved: 1 | Pending approval: 1');
    }

    public function test_respects_selected_date_range(): void
    {
        $manager = User::factory()->manager()->create();
        $salesAgent = User::factory()->sales()->create();

        $this->actingAs($manager);

        $this->officeSale($salesAgent, 'approved', 5000, now()->subDays(5));
        $this->officeSale($salesAgent, 'approved', 3000, now());

        Session::put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
        Session::put('dashboard_date_to', now()->endOfDay()->toDateTimeString());

        Livewire::test(OfficeSalesStatsWidget::class)
            ->assertSee('₦3,000.00')
            ->assertDontSee('₦8,000.00');
    }

    public function test_is_hidden_for_non_office_sales_roles(): void
    {
        $user = User::factory()->communitySalesRepresentative()->create();

        $this->actingAs($user);

        $this->assertFalse(OfficeSalesStatsWidget::canView());
    }

    private function officeSale(User $user, string $status, float $total, ?\DateTimeInterface $createdAt = null): SalesRecord
    {
        return SalesRecord::factory()->create([
            'agent_id' => $user->id,
            'agent_type' => 'sales',
            'status' => $status,
            'total_value' => $total,
            'created_at' => $createdAt ?? now(),
        ]);
    }
}
