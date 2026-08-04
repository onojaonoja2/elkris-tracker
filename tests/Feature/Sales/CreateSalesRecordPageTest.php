<?php

namespace Tests\Feature\Sales;

use App\Filament\Resources\SalesRecords\Pages\CreateSalesRecord;
use App\Models\AgentStock;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateSalesRecordPageTest extends TestCase
{
    use RefreshDatabase;

    private User $retailUser;

    private ProductType $productType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->retailUser = User::factory()->state(['role' => 'retail_market'])->create();
        $this->productType = ProductType::factory()->create([
            'name' => 'Ora herbal mix',
            'available_grammages' => [100, 200, 500],
        ]);

        $this->actingAs($this->retailUser);
    }

    private function formData(int $quantity): array
    {
        return [
            'business_name' => 'Peter Retail Store',
            'is_credit' => true,
            'customer_name' => 'Jane Doe',
            'customer_phone' => '08012345678',
            'expected_collection_date' => now()->addDays(7)->toDateString(),
            'products' => [[
                'product_name' => 'Ora herbal mix',
                'grammage' => '100',
                'quantity' => $quantity,
                'price' => 1000,
            ]],
            'total_value' => 1000 * $quantity,
        ];
    }

    public function test_retail_user_can_create_sales_record_and_gets_success_toast(): void
    {
        AgentStock::factory()->create([
            'user_id' => $this->retailUser->id,
            'product_type_id' => $this->productType->id,
            'product_name' => 'Ora herbal mix',
            'grammage' => 100,
            'quantity' => 20,
        ]);

        Livewire::test(CreateSalesRecord::class)
            ->fillForm($this->formData(5))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();

        $this->assertDatabaseHas('sales_records', [
            'agent_id' => $this->retailUser->id,
            'agent_type' => 'retail_market',
            'status' => 'pending',
            'is_credit' => true,
        ]);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $this->retailUser->id,
            'product_name' => 'Ora herbal mix',
            'grammage' => 100,
            'quantity' => 15,
        ]);
    }

    public function test_retail_user_gets_danger_toast_when_stock_is_insufficient(): void
    {
        AgentStock::factory()->create([
            'user_id' => $this->retailUser->id,
            'product_type_id' => $this->productType->id,
            'product_name' => 'Ora herbal mix',
            'grammage' => 100,
            'quantity' => 2,
        ]);

        Livewire::test(CreateSalesRecord::class)
            ->fillForm($this->formData(5))
            ->call('create')
            ->assertHasErrors(['products'])
            ->assertNotified();

        $this->assertDatabaseMissing('sales_records', [
            'agent_id' => $this->retailUser->id,
        ]);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $this->retailUser->id,
            'product_name' => 'Ora herbal mix',
            'grammage' => 100,
            'quantity' => 2,
        ]);
    }

    public function test_retail_user_gets_form_error_when_business_name_is_missing(): void
    {
        $data = $this->formData(1);
        $data['business_name'] = null;

        Livewire::test(CreateSalesRecord::class)
            ->fillForm($data)
            ->call('create')
            ->assertHasFormErrors(['business_name']);

        $this->assertDatabaseMissing('sales_records', [
            'agent_id' => $this->retailUser->id,
        ]);
    }
}
