<?php

namespace Tests\Feature\Sales;

use App\Filament\Resources\SalesRecords\Pages\CreateSalesRecord;
use App\Models\AgentStock;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SalesRecordCustomerInfoTest extends TestCase
{
    use RefreshDatabase;

    private User $csr;

    private ProductType $productType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csr = User::factory()->communitySalesRepresentative()->create();
        $this->productType = ProductType::factory()->create([
            'name' => 'Ora herbal mix',
            'available_grammages' => [
                ['grammage' => 100, 'carton_quantity' => 20],
                200,
                500,
            ],
        ]);

        AgentStock::factory()->create([
            'user_id' => $this->csr->id,
            'product_type_id' => $this->productType->id,
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => 50,
        ]);

        $this->actingAs($this->csr);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productLine(int $quantity = 2): array
    {
        return [[
            'product_name' => $this->productType->name,
            'grammage' => '100',
            'cartons' => 0,
            'pieces' => $quantity,
            'quantity' => $quantity,
            'price' => 1000,
        ]];
    }

    public function test_cash_sale_shows_customer_fields_and_hides_credit_only_fields(): void
    {
        Livewire::test(CreateSalesRecord::class)
            ->assertFormFieldVisible('customer_name')
            ->assertFormFieldVisible('customer_phone')
            ->assertFormFieldHidden('customer_id')
            ->assertFormFieldHidden('expected_collection_date');
    }

    public function test_credit_sale_still_requires_customer_name(): void
    {
        Livewire::test(CreateSalesRecord::class)
            ->fillForm([
                'is_credit' => true,
                'customer_phone' => '08012345678',
                'expected_collection_date' => now()->addDays(7)->toDateString(),
                'products' => $this->productLine(),
            ])
            ->call('create')
            ->assertHasFormErrors(['customer_name']);

        $this->assertDatabaseMissing('sales_records', ['agent_id' => $this->csr->id]);
    }

    public function test_cash_sale_saves_customer_name_and_phone(): void
    {
        Storage::fake('s3');

        Livewire::test(CreateSalesRecord::class)
            ->fillForm([
                'is_credit' => false,
                'customer_name' => 'Chinedu Okafor',
                'customer_phone' => '08012345678',
                'receipt_path' => UploadedFile::fake()->image('receipt.png'),
                'products' => $this->productLine(),
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('sales_records', [
            'agent_id' => $this->csr->id,
            'is_credit' => false,
            'customer_name' => 'Chinedu Okafor',
            'customer_phone' => '08012345678',
        ]);
    }

    public function test_cash_sale_without_customer_name_still_succeeds(): void
    {
        Storage::fake('s3');

        Livewire::test(CreateSalesRecord::class)
            ->fillForm([
                'is_credit' => false,
                'receipt_path' => UploadedFile::fake()->image('receipt.png'),
                'products' => $this->productLine(),
            ])
            ->call('create')
            ->assertHasNoFormErrors(['customer_name'])
            ->assertRedirect();

        $this->assertDatabaseHas('sales_records', [
            'agent_id' => $this->csr->id,
            'is_credit' => false,
            'customer_name' => null,
        ]);
    }
}
