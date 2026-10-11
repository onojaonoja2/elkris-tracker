<?php

namespace Tests\Feature\Dashboards;

use App\Filament\Pages\AccountantDashboard;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\GeneralAccountantDashboard;
use App\Filament\Pages\GeneralManagerDashboard;
use App\Filament\Pages\ManagerDashboard;
use App\Filament\Widgets\ManagerStatsWidget;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerProspectLoyalCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prospect_and_loyal_scopes_ignore_cancelled_but_count_migrated_orders(): void
    {
        $loyalWithMigratedOrder = Customer::factory()->create();
        $loyalWithMigratedOrder->orders()->create([
            'status' => 'delivered',
            'total_price' => 1000,
            'is_migrated_order' => true,
        ]);

        $cancelledOnly = Customer::factory()->create();
        $cancelledOnly->orders()->create([
            'status' => 'cancelled',
            'total_price' => 500,
            'is_migrated_order' => false,
        ]);

        Customer::factory()->create();

        $this->assertSame(1, Customer::hasPlacedOrder()->count());
        $this->assertSame(2, Customer::hasNotPlacedOrder()->count());
        $this->assertSame(3, Customer::count());
    }

    public function test_manager_stats_widget_shows_prospect_and_loyal_cards(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $rep = User::factory()->rep()->create();

        $loyalCustomer = Customer::factory()->repId($rep)->create();
        $loyalCustomer->orders()->create([
            'status' => 'delivered',
            'total_price' => 1000,
            'is_migrated_order' => false,
        ]);

        Customer::factory()->create();

        Session::put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
        Session::put('dashboard_date_to', now()->endOfDay()->toDateTimeString());

        Livewire::test(ManagerStatsWidget::class)
            ->assertSee('Prospects')
            ->assertSee('Loyal Customers');
    }

    public function test_manager_dashboard_opens_prospect_and_loyal_breakdowns(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(ManagerDashboard::class)
            ->dispatch('open-prospect-breakdown')
            ->assertActionMounted('prospectBreakdown');

        Livewire::test(ManagerDashboard::class)
            ->dispatch('open-loyal-breakdown')
            ->assertActionMounted('loyalBreakdown');
    }

    public function test_accountant_dashboard_opens_prospect_and_loyal_breakdowns(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        Livewire::test(AccountantDashboard::class)
            ->dispatch('open-prospect-breakdown')
            ->assertActionMounted('prospectBreakdown');

        Livewire::test(AccountantDashboard::class)
            ->dispatch('open-loyal-breakdown')
            ->assertActionMounted('loyalBreakdown');
    }

    public function test_general_manager_dashboard_opens_prospect_and_loyal_breakdowns(): void
    {
        $generalManager = User::factory()->generalManager()->create();
        $this->actingAs($generalManager);

        Livewire::test(GeneralManagerDashboard::class)
            ->dispatch('open-prospect-breakdown')
            ->assertActionMounted('prospectBreakdown');

        Livewire::test(GeneralManagerDashboard::class)
            ->dispatch('open-loyal-breakdown')
            ->assertActionMounted('loyalBreakdown');
    }

    public function test_general_accountant_dashboard_opens_prospect_and_loyal_breakdowns(): void
    {
        $generalAccountant = User::factory()->state(['role' => 'general_accountant'])->create();
        $this->actingAs($generalAccountant);

        Livewire::test(GeneralAccountantDashboard::class)
            ->dispatch('open-prospect-breakdown')
            ->assertActionMounted('prospectBreakdown');

        Livewire::test(GeneralAccountantDashboard::class)
            ->dispatch('open-loyal-breakdown')
            ->assertActionMounted('loyalBreakdown');
    }

    public function test_fallback_dashboard_registers_prospect_and_loyal_breakdown_actions(): void
    {
        $stockist = User::factory()->state(['role' => 'stockist'])->create();
        $this->actingAs($stockist);

        Livewire::test(Dashboard::class)
            ->assertActionExists('prospectBreakdown')
            ->assertActionExists('loyalBreakdown');
    }
}
