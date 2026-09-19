<?php

namespace App\Services;

use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\StockCount;
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

            foreach ($record->items as $item) {
                if ($record->warehouse_id) {
                    self::withWarehouseStock($record, $item);
                } else {
                    self::withAgentStock($record, $item);
                }
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

    private static function withWarehouseStock(StockCount $record, mixed $item): void
    {
        $attributes = [
            'warehouse_id' => $record->warehouse_id,
            'product_type_id' => $item->product_type_id,
            'grammage' => $item->grammage,
        ];

        if ($record->is_additional_count) {
            Inventory::firstOrCreate($attributes, ['quantity' => 0])
                ->increment('quantity', $item->quantity);

            return;
        }

        Inventory::updateOrCreate($attributes, ['quantity' => $item->quantity]);
    }

    private static function withAgentStock(StockCount $record, mixed $item): void
    {
        $attributes = [
            'user_id' => $record->user_id,
            'product_type_id' => $item->product_type_id,
            'product_name' => $item->product_name ?? $item->productType?->name ?? 'Unknown',
            'grammage' => $item->grammage,
        ];

        if ($record->is_additional_count) {
            AgentStock::firstOrCreate($attributes, ['quantity' => 0])
                ->increment('quantity', $item->quantity);

            return;
        }

        AgentStock::updateOrCreate($attributes, ['quantity' => $item->quantity]);
    }
}
