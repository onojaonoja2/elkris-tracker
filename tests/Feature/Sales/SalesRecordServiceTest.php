<?php

namespace Tests\Feature\Sales;

use App\Models\AgentStock;
use App\Models\ProductType;
use App\Models\User;
use App\Services\SalesRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesRecordServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function buildProducts(): array
    {
        $productType = ProductType::factory()->create([
            'name' => 'Ora Herbal Mix',
            'available_grammages' => [['grammage' => 100, 'carton_quantity' => 20]],
        ]);

        return [
            $productType,
            [
                'product_name' => $productType->name,
                'grammage' => 100,
                'quantity' => 5,
                'price' => 1000.00,
            ],
        ];
    }

    public function test_submit_sale_deducts_stock_and_creates_pending_record(): void
    {
        [$productType, $product] = $this->buildProducts();
        $agent = User::factory()->communitySalesRepresentative()->create();
        $stock = AgentStock::factory()->create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 20,
        ]);

        $this->actingAs($agent);

        $record = SalesRecordService::submitSale([
            'products' => [$product],
            'total_value' => 5000.00,
            'is_credit' => false,
        ]);

        $this->assertDatabaseHas('sales_records', [
            'id' => $record->id,
            'agent_id' => $agent->id,
            'status' => 'pending',
            'is_credit' => false,
            'stock_deducted_at' => now(),
        ]);

        $stock->refresh();
        $this->assertEquals(15, $stock->quantity);
    }

    public function test_submit_sale_fails_when_insufficient_stock(): void
    {
        [$productType, $product] = $this->buildProducts();
        $agent = User::factory()->communitySalesRepresentative()->create();
        AgentStock::factory()->create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 2,
        ]);

        $this->actingAs($agent);
        $this->expectException(ValidationException::class);

        SalesRecordService::submitSale([
            'products' => [$product],
            'total_value' => 5000.00,
            'is_credit' => false,
        ]);
    }

    public function test_approve_paid_sale_credits_stock_balance(): void
    {
        [$productType, $product] = $this->buildProducts();
        $agent = User::factory()->communitySalesRepresentative()->create([
            'stock_balance' => 1000.00,
        ]);
        AgentStock::factory()->create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 20,
        ]);

        $this->actingAs($agent);
        $record = SalesRecordService::submitSale([
            'products' => [$product],
            'total_value' => 5000.00,
            'is_credit' => false,
        ]);

        $accountant = User::factory()->accountant()->create();
        SalesRecordService::approve($record, [], $accountant->id);

        $agent->refresh();
        $this->assertEquals(6000.00, (float) $agent->stock_balance);
        $this->assertEquals('approved', $record->fresh()->status);
    }

    public function test_approve_credit_sale_does_not_credit_stock_balance(): void
    {
        [$productType, $product] = $this->buildProducts();
        $agent = User::factory()->communitySalesRepresentative()->create([
            'stock_balance' => 1000.00,
        ]);
        AgentStock::factory()->create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 20,
        ]);

        $this->actingAs($agent);
        $record = SalesRecordService::submitSale([
            'products' => [$product],
            'total_value' => 5000.00,
            'is_credit' => true,
        ]);

        $accountant = User::factory()->accountant()->create();
        SalesRecordService::approve($record, [], $accountant->id);

        $agent->refresh();
        $this->assertEquals(1000.00, (float) $agent->stock_balance);
        $this->assertEquals('approved', $record->fresh()->status);
    }

    public function test_reject_restores_stock(): void
    {
        [$productType, $product] = $this->buildProducts();
        $agent = User::factory()->communitySalesRepresentative()->create();
        $stock = AgentStock::factory()->create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 20,
        ]);

        $this->actingAs($agent);
        $record = SalesRecordService::submitSale([
            'products' => [$product],
            'total_value' => 5000.00,
            'is_credit' => false,
        ]);

        $accountant = User::factory()->accountant()->create();
        SalesRecordService::reject($record, 'Invalid receipt', $accountant->id);

        $stock->refresh();
        $this->assertEquals(20, $stock->quantity);
        $this->assertEquals('rejected', $record->fresh()->status);
        $this->assertEquals('Invalid receipt', $record->fresh()->rejection_reason);
    }

    public function test_mark_collected_requires_payment_proof(): void
    {
        [$productType, $product] = $this->buildProducts();
        $agent = User::factory()->communitySalesRepresentative()->create([
            'stock_balance' => 1000.00,
        ]);
        AgentStock::factory()->create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 20,
        ]);

        $this->actingAs($agent);
        $record = SalesRecordService::submitSale([
            'products' => [$product],
            'total_value' => 5000.00,
            'is_credit' => true,
        ]);

        $accountant = User::factory()->accountant()->create();
        SalesRecordService::approve($record, [], $accountant->id);

        $this->expectException(ValidationException::class);
        SalesRecordService::markCollected($record, [], $accountant->id);
    }

    public function test_mark_collected_credits_stock_balance_for_credit_sale(): void
    {
        [$productType, $product] = $this->buildProducts();
        $agent = User::factory()->communitySalesRepresentative()->create([
            'stock_balance' => 1000.00,
        ]);
        AgentStock::factory()->create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 20,
        ]);

        $this->actingAs($agent);
        $record = SalesRecordService::submitSale([
            'products' => [$product],
            'total_value' => 5000.00,
            'is_credit' => true,
        ]);

        $accountant = User::factory()->accountant()->create();
        SalesRecordService::approve($record, [], $accountant->id);
        SalesRecordService::attachPaymentProof($record, ['payment_proof_path' => 'proofs/test.jpg'], $accountant->id);
        SalesRecordService::markCollected($record, ['credit_notes' => 'Paid in cash'], $accountant->id);

        $agent->refresh();
        $this->assertEquals(6000.00, (float) $agent->stock_balance);
        $this->assertEquals('collected', $record->fresh()->credit_status);
    }

    public function test_office_sale_submission_creates_pending_record(): void
    {
        [$productType, $product] = $this->buildProducts();
        $sales = User::factory()->sales()->create();
        $stock = AgentStock::factory()->create([
            'user_id' => $sales->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 20,
        ]);

        $this->actingAs($sales);
        $record = SalesRecordService::submitSale([
            'agent_type' => 'sales',
            'products' => [$product],
            'total_value' => 5000.00,
            'is_credit' => false,
        ], $sales->id);

        $this->assertDatabaseHas('sales_records', [
            'id' => $record->id,
            'agent_type' => 'sales',
            'status' => 'pending',
        ]);

        $stock->refresh();
        $this->assertEquals(15, $stock->quantity);
    }
}
