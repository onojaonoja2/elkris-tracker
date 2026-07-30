<?php

namespace App\Services;

use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\WarehouseReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseReturnService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function submit(array $data, int $userId): WarehouseReturn
    {
        return DB::transaction(function () use ($data, $userId) {
            $available = AgentStock::where([
                'user_id' => $userId,
                'product_type_id' => $data['product_type_id'],
                'grammage' => $data['grammage'],
            ])->sum('quantity');

            if ($available < $data['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => "You only have {$available} pieces of this product in your stock. Cannot return {$data['quantity']}.",
                ]);
            }

            return WarehouseReturn::create([
                'user_id' => $userId,
                'warehouse_id' => $data['warehouse_id'],
                'product_type_id' => $data['product_type_id'],
                'grammage' => $data['grammage'],
                'quantity' => $data['quantity'],
                'reason' => $data['reason'] ?? null,
                'status' => 'pending',
            ]);
        });
    }

    public static function approve(WarehouseReturn $return, int $approvedBy): void
    {
        if (! $return->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending warehouse returns can be approved.',
            ]);
        }

        DB::transaction(function () use ($return, $approvedBy) {
            $stock = AgentStock::where([
                'user_id' => $return->user_id,
                'product_type_id' => $return->product_type_id,
                'grammage' => $return->grammage,
            ])->first();

            if (! $stock || $stock->quantity < $return->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'The agent no longer has enough stock to fulfill this return.',
                ]);
            }

            $stock->decrement('quantity', $return->quantity);

            $inventory = Inventory::firstOrCreate(
                [
                    'warehouse_id' => $return->warehouse_id,
                    'product_type_id' => $return->product_type_id,
                    'grammage' => $return->grammage,
                ],
                ['quantity' => 0]
            );
            $inventory->increment('quantity', $return->quantity);

            $return->update([
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);
        });
    }

    public static function reject(WarehouseReturn $return, string $reason, int $rejectedBy): void
    {
        if (! $return->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending warehouse returns can be rejected.',
            ]);
        }

        $return->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => $rejectedBy,
            'approved_at' => now(),
        ]);
    }
}
