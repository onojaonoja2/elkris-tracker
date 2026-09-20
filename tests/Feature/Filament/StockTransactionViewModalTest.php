<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\StockTransactions\Pages\ManageStockTransactions;
use App\Models\StockTransaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockTransactionViewModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_transaction_view_action_opens_modal_with_details(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $admin = User::factory()->admin()->create(['name' => 'Tina Admin']);
        $warehouse = Warehouse::factory()->create(['name' => 'Main Hub']);

        $transaction = StockTransaction::create([
            'type' => 'received',
            'transaction_date' => now()->toDateString(),
            'product_name' => 'African bitters',
            'grammage' => 100,
            'quantity' => 10,
            'disbursed_to' => 'Additional stock count #1',
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
        ]);

        Livewire::test(ManageStockTransactions::class)
            ->assertTableActionExists('view')
            ->mountTableAction('view', $transaction->id)
            ->assertHasNoActionErrors()
            ->assertMountedActionModalSee('African bitters')
            ->assertMountedActionModalSee('Main Hub')
            ->assertMountedActionModalSee('Tina Admin');
    }

    public function test_warehouse_manager_can_view_own_warehouse_transactions(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Main Hub', 'manager_id' => $manager->id]);

        $transaction = StockTransaction::create([
            'type' => 'disbursed',
            'transaction_date' => now()->toDateString(),
            'product_name' => 'Ora herbal mix',
            'grammage' => 500,
            'quantity' => 3,
            'disbursed_to' => 'Direct sale',
            'user_id' => $manager->id,
            'warehouse_id' => $warehouse->id,
        ]);

        $this->actingAs($manager);

        Livewire::test(ManageStockTransactions::class)
            ->assertCanSeeTableRecords([$transaction])
            ->assertTableActionExists('view')
            ->mountTableAction('view', $transaction->id)
            ->assertHasNoActionErrors()
            ->assertMountedActionModalSee('Ora herbal mix');
    }
}
