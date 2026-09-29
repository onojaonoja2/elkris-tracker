<?php

namespace App\Services;

use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockCountService
{
    public static function finalApprove(StockCount $record, int $approvedBy): void
    {
        if ($record->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending stock counts can be approved.',
            ]);
        }

        DB::transaction(function () use ($record, $approvedBy) {
            $record->update([
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);

            if ($record->warehouse_id) {
                self::withWarehouseStock($record);
            } else {
                self::withAgentStock($record);
            }
        });
    }

    public static function reject(StockCount $record, string $reason): void
    {
        if ($record->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending stock counts can be rejected.',
            ]);
        }

        $record->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * An initial count replaces the warehouse's entire inventory with the counted
     * items; an additional count increments existing quantities on top of them.
     */
    private static function withWarehouseStock(StockCount $record): void
    {
        if ($record->is_additional_count) {
            foreach ($record->items as $item) {
                Inventory::firstOrCreate([
                    'warehouse_id' => $record->warehouse_id,
                    'product_type_id' => $item->product_type_id,
                    'grammage' => $item->grammage,
                ], ['quantity' => 0])
                    ->increment('quantity', $item->quantity);

                self::logAdditionalTransaction($record, $item);
            }

            return;
        }

        Inventory::where('warehouse_id', $record->warehouse_id)->delete();

        foreach ($record->items as $item) {
            Inventory::updateOrCreate([
                'warehouse_id' => $record->warehouse_id,
                'product_type_id' => $item->product_type_id,
                'grammage' => $item->grammage,
            ], ['quantity' => $item->quantity]);
        }
    }

    /**
     * An initial count replaces the agent's entire stock with the counted items;
     * an additional count increments existing quantities on top of them.
     */
    private static function withAgentStock(StockCount $record): void
    {
        if ($record->is_additional_count) {
            foreach ($record->items as $item) {
                AgentStock::firstOrCreate(
                    self::agentStockKey($record, $item),
                    [
                        'product_type_id' => $item->product_type_id,
                        'quantity' => 0,
                    ]
                )->increment('quantity', $item->quantity);

                self::logAdditionalTransaction($record, $item);
            }

            return;
        }

        AgentStock::where('user_id', $record->user_id)->delete();

        foreach ($record->items as $item) {
            AgentStock::updateOrCreate(
                self::agentStockKey($record, $item),
                [
                    'product_type_id' => $item->product_type_id,
                    'quantity' => $item->quantity,
                ]
            );
        }
    }

    /**
     * Matches the unique index on agent_stocks (user_id, product_name, grammage).
     *
     * @return array{user_id: int, product_name: string, grammage: int}
     */
    private static function agentStockKey(StockCount $record, StockCountItem $item): array
    {
        return [
            'user_id' => $record->user_id,
            'product_name' => $item->product_name ?? $item->productType?->name ?? 'Unknown',
            'grammage' => $item->grammage,
        ];
    }

    private static function logAdditionalTransaction(StockCount $record, StockCountItem $item): void
    {
        StockTransaction::create([
            'type' => 'received',
            'transaction_date' => now()->toDateString(),
            'product_type_id' => $item->product_type_id,
            'product_name' => $item->product_name ?? $item->productType?->name ?? 'Unknown',
            'grammage' => $item->grammage,
            'quantity' => $item->quantity,
            'disbursed_to' => 'Additional stock count #'.$record->id,
            'user_id' => $record->user_id,
            'warehouse_id' => $record->warehouse_id,
        ]);
    }
}
