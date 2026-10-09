<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\AccountantCustomersWidget;
use App\Filament\Widgets\ManagerCustomersWidget;
use App\Filament\Widgets\ManagerStatsWidget;
use App\Livewire\CustomersAddedTodayTable;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

class UnassignedCustomerExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_stats_exclude_unassigned_customers(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Session::put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
        Session::put('dashboard_date_to', now()->endOfDay()->toDateTimeString());

        $rep = User::factory()->rep()->create();

        Customer::factory()->repId($rep)->count(13)->create();
        Customer::factory()->count(4)->create();

        $component = Livewire::test(ManagerStatsWidget::class)->instance();

        $stats = (new \ReflectionMethod(ManagerStatsWidget::class, 'getStats'))->invoke($component);

        $byLabel = collect($stats)->keyBy(fn ($stat) => $stat->getLabel());

        $this->assertSame(13, $byLabel['Total Customers']->getValue());
        $this->assertSame(13, $byLabel['Customers Added Today']->getValue());
        $this->assertSame(13, $byLabel['Prospects']->getValue());
        $this->assertSame(0, $byLabel['Loyal Customers']->getValue());
    }

    public function test_manager_system_customers_table_excludes_unassigned_but_includes_left_for_manager(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $rep = User::factory()->rep()->create();

        $assigned = Customer::factory()->repId($rep)->create();
        $leftForManager = Customer::factory()->create(['manager_visible_at' => now()]);
        $hiddenUnassigned = Customer::factory()->create();

        Livewire::test(ManagerCustomersWidget::class)
            ->assertCanSeeTableRecords([$assigned, $leftForManager])
            ->assertCanNotSeeTableRecords([$hiddenUnassigned]);
    }

    public function test_accountant_system_customers_table_excludes_all_unassigned(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $rep = User::factory()->rep()->create();

        $assigned = Customer::factory()->repId($rep)->create();
        $leftForManager = Customer::factory()->create(['manager_visible_at' => now()]);

        Livewire::test(AccountantCustomersWidget::class)
            ->assertCanSeeTableRecords([$assigned])
            ->assertCanNotSeeTableRecords([$leftForManager]);
    }

    public function test_customers_added_today_table_excludes_unassigned(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $rep = User::factory()->rep()->create();

        $assigned = Customer::factory()->repId($rep)->create(['customer_name' => 'Assigned Buyer']);
        Customer::factory()->create(['customer_name' => 'Unassigned Buyer']);

        $component = Livewire::test(CustomersAddedTodayTable::class);

        $customerIds = $component->instance()->customers->pluck('id')->all();

        $this->assertContains($assigned->id, $customerIds);
        $this->assertNotContains(Customer::where('customer_name', 'Unassigned Buyer')->first()->id, $customerIds);
    }
}
