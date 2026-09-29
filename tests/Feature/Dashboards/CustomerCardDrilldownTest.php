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

class CustomerCardDrilldownTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_total_customers_card_counts_all_time_and_has_no_conversion_label(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Customer::factory()->create(['created_at' => now()->subDays(10)]);

        Session::put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
        Session::put('dashboard_date_to', now()->endOfDay()->toDateTimeString());

        Livewire::test(ManagerStatsWidget::class)
            ->assertSee('Total Customers')
            ->assertDontSee('conversion rate')
            ->assertDontSee('0%');
    }

    public function test_admin_manager_dashboard_opens_customer_breakdown(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(ManagerDashboard::class)
            ->dispatch('open-customer-breakdown')
            ->assertActionMounted('customerBreakdown');
    }

    public function test_manager_dashboard_opens_customer_breakdown(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager);

        Livewire::test(ManagerDashboard::class)
            ->dispatch('open-customer-breakdown')
            ->assertActionMounted('customerBreakdown');
    }

    public function test_accountant_dashboard_opens_customer_breakdown(): void
    {
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($accountant);

        Livewire::test(AccountantDashboard::class)
            ->dispatch('open-customer-breakdown')
            ->assertActionMounted('customerBreakdown');
    }

    public function test_general_manager_dashboard_opens_customer_breakdown(): void
    {
        $generalManager = User::factory()->generalManager()->create();

        $this->actingAs($generalManager);

        Livewire::test(GeneralManagerDashboard::class)
            ->dispatch('open-customer-breakdown')
            ->assertActionMounted('customerBreakdown');
    }

    public function test_general_accountant_dashboard_opens_customer_breakdown(): void
    {
        $generalAccountant = User::factory()->state(['role' => 'general_accountant'])->create();

        $this->actingAs($generalAccountant);

        Livewire::test(GeneralAccountantDashboard::class)
            ->dispatch('open-customer-breakdown')
            ->assertActionMounted('customerBreakdown');
    }

    public function test_fallback_dashboard_registers_customer_breakdown_action(): void
    {
        $stockist = User::factory()->state(['role' => 'stockist'])->create();

        $this->actingAs($stockist);

        Livewire::test(Dashboard::class)
            ->assertActionExists('customerBreakdown')
            ->assertActionExists('customersAddedToday');
    }

    public function test_manager_dashboard_opens_customers_added_today(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager);

        Livewire::test(ManagerDashboard::class)
            ->dispatch('open-customers-added-today')
            ->assertActionMounted('customersAddedToday');
    }
}
