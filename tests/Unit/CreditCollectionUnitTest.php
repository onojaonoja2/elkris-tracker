<?php

namespace Tests\Unit;

use App\Models\CreditCollection;
use App\Models\SalesRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CreditCollectionUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_belongs_to_sales_record(): void
    {
        $record = SalesRecord::factory()->create();
        $collection = CreditCollection::factory()->create(['sales_record_id' => $record->id]);

        $this->assertTrue($collection->salesRecord->is($record));
    }

    public function test_collection_belongs_to_collector_user_via_collected_by(): void
    {
        $collector = User::factory()->accountant()->create();
        $collection = CreditCollection::factory()->create(['collected_by' => $collector->id]);

        $this->assertTrue($collection->collector->is($collector));
    }

    public function test_collected_amount_is_cast_to_decimal(): void
    {
        $collection = CreditCollection::factory()->create(['collected_amount' => 2500.5]);

        $this->assertSame('2500.50', (string) $collection->collected_amount);
        $this->assertSame(2500.50, (float) $collection->collected_amount);
    }

    public function test_notes_are_sanitized_on_save(): void
    {
        $collection = CreditCollection::factory()->create(['notes' => '<script>alert(1)</script>Paid in cash']);

        $this->assertStringNotContainsString('<script>', $collection->fresh()->notes);
        $this->assertStringContainsString('Paid in cash', $collection->fresh()->notes);
    }

    public function test_collected_at_is_cast_to_datetime(): void
    {
        $collection = CreditCollection::factory()->create(['collected_at' => now()]);

        $this->assertInstanceOf(Carbon::class, $collection->collected_at);
    }
}
