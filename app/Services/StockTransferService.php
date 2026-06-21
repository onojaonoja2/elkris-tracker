<?php

namespace App\Services;

use App\Enums\StockTransferStatus;
use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    public static function receive(StockTransfer $record, array $items): void
    {
        DB::transaction(function () use ($record, $items) {
            foreach ($items as $itemData) {
                $item = $record->items()->find($itemData['item_id']);
                if (! $item) {
                    continue;
                }

                $accepted = (int) ($itemData['accepted_quantity'] ?? $item->quantity);
                $rejected = (int) ($itemData['rejected_quantity'] ?? 0);

                $item->update([
                    'rejected_quantity' => $rejected,
                    'rejection_reason' => $rejected > 0 ? ($itemData['rejection_reason'] ?? null) : null,
                ]);

                $accepted = min($accepted, $item->quantity);

                if ($accepted > 0) {
                    if ($record->to_warehouse_id) {
                        $inv = Inventory::firstOrCreate(
                            [
                                'warehouse_id' => $record->to_warehouse_id,
                                'product_type_id' => $item->product_type_id,
                                'grammage' => $item->grammage,
                            ],
                            ['quantity' => 0]
                        );
                        $inv->increment('quantity', $accepted);
                    }

                    if ($record->to_agent_id) {
                        $agentStock = AgentStock::firstOrCreate(
                            [
                                'user_id' => $record->to_agent_id,
                                'product_type_id' => $item->product_type_id,
                                'product_name' => $item->productType?->name ?? 'Unknown',
                                'grammage' => $item->grammage,
                            ],
                            ['quantity' => 0]
                        );
                        $agentStock->increment('quantity', $accepted);
                    }

                    if ($record->requested_by && $record->requested_by !== $record->to_agent_id) {
                        $agentStock = AgentStock::firstOrCreate(
                            [
                                'user_id' => $record->requested_by,
                                'product_type_id' => $item->product_type_id,
                                'product_name' => $item->productType?->name ?? 'Unknown',
                                'grammage' => $item->grammage,
                            ],
                            ['quantity' => 0]
                        );
                        $agentStock->increment('quantity', $accepted);
                    }
                }
            }

            if ($record->from_warehouse_id) {
                foreach ($record->items as $item) {
                    $inv = Inventory::where([
                        'warehouse_id' => $record->from_warehouse_id,
                        'product_type_id' => $item->product_type_id,
                        'grammage' => $item->grammage,
                    ])->first();
                    if ($inv) {
                        $inv->decrement('quantity', $item->quantity - $item->rejected_quantity);
                    }
                }
            }

            if ($record->from_agent_id) {
                foreach ($record->items as $item) {
                    $stock = AgentStock::where([
                        'user_id' => $record->from_agent_id,
                        'product_name' => $item->productType?->name ?? 'Unknown',
                        'grammage' => $item->grammage,
                    ])->first();
                    if ($stock) {
                        $stock->decrement('quantity', $item->quantity - $item->rejected_quantity);
                    }
                }
            }

            $record->update([
                'status' => StockTransferStatus::Received,
                'received_by' => auth()->id(),
                'received_at' => now(),
            ]);
        });
    }

    public static function approve(StockTransfer $record, bool $validateInventory = false): void
    {
        DB::transaction(function () use ($record, $validateInventory) {
            if ($validateInventory) {
                $insufficientItems = [];

                foreach ($record->items as $item) {
                    $inventory = Inventory::where([
                        'warehouse_id' => $record->from_warehouse_id,
                        'product_type_id' => $item->product_type_id,
                        'grammage' => $item->grammage,
                    ])->first();

                    $available = $inventory?->quantity ?? 0;

                    if ($available < $item->quantity) {
                        $productName = $item->productType?->name ?? 'Unknown';
                        $insufficientItems[] = "{$productName} {$item->grammage}g (requested: {$item->quantity}, available: {$available})";
                    }
                }

                if (! empty($insufficientItems)) {
                    Notification::make()
                        ->title('Insufficient stock in warehouse')
                        ->body('The following items have insufficient stock:'.PHP_EOL.implode(PHP_EOL, $insufficientItems))
                        ->danger()
                        ->send();

                    return;
                }
            }

            $record->update([
                'status' => StockTransferStatus::Approved,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });
    }

    public static function reject(StockTransfer $record, string $reason): void
    {
        DB::transaction(function () use ($record, $reason) {
            $record->update([
                'status' => StockTransferStatus::Cancelled,
                'rejection_reason' => $reason,
            ]);
        });
    }

    public static function collect(StockTransfer $record, array $items): void
    {
        DB::transaction(function () use ($record, $items) {
            $record->update([
                'status' => StockTransferStatus::Collected,
                'collected_by' => auth()->id(),
                'collected_at' => now(),
                'source_type' => 'agent_collection',
            ]);

            foreach ($items as $itemData) {
                $record->items()->create([
                    'product_type_id' => $itemData['product_type_id'],
                    'grammage' => $itemData['grammage'],
                    'quantity' => $itemData['quantity'],
                ]);
            }
        });
    }

    public static function reassign(StockTransfer $record, array $data): void
    {
        DB::transaction(function () use ($record, $data) {
            $toWarehouseId = $data['to_warehouse_id'] ?? null;
            $toAgentId = $data['to_agent_id'] ?? null;

            if ($record->from_agent_id) {
                foreach ($record->items as $item) {
                    $stock = AgentStock::where([
                        'user_id' => $record->from_agent_id,
                        'product_name' => $item->productType?->name ?? 'Unknown',
                        'grammage' => $item->grammage,
                    ])->first();

                    if ($stock) {
                        $stock->decrement('quantity', $item->quantity);
                    }

                    StockTransaction::create([
                        'type' => 'disbursed',
                        'transaction_date' => now()->toDateString(),
                        'product_type_id' => $item->product_type_id,
                        'product_name' => $item->productType?->name ?? 'Unknown',
                        'grammage' => $item->grammage,
                        'quantity' => $item->quantity,
                        'disbursed_to' => $toWarehouseId
                            ? 'Warehouse #'.$toWarehouseId
                            : ($toAgentId ? 'Agent #'.$toAgentId : 'Unknown'),
                        'user_id' => $record->from_agent_id,
                        'warehouse_id' => $toWarehouseId,
                    ]);
                }
            }

            foreach ($record->items as $item) {
                $accepted = $item->quantity;

                if ($accepted > 0) {
                    if ($toWarehouseId) {
                        $inv = Inventory::firstOrCreate(
                            [
                                'warehouse_id' => $toWarehouseId,
                                'product_type_id' => $item->product_type_id,
                                'grammage' => $item->grammage,
                            ],
                            ['quantity' => 0]
                        );
                        $inv->increment('quantity', $accepted);

                        StockTransaction::create([
                            'type' => 'received',
                            'transaction_date' => now()->toDateString(),
                            'product_type_id' => $item->product_type_id,
                            'product_name' => $item->productType?->name ?? 'Unknown',
                            'grammage' => $item->grammage,
                            'quantity' => $accepted,
                            'disbursed_to' => 'Collected from Agent #'.$record->from_agent_id,
                            'user_id' => auth()->id(),
                            'warehouse_id' => $toWarehouseId,
                        ]);
                    }

                    if ($toAgentId) {
                        $agentStock = AgentStock::firstOrCreate(
                            [
                                'user_id' => $toAgentId,
                                'product_type_id' => $item->product_type_id,
                                'product_name' => $item->productType?->name ?? 'Unknown',
                                'grammage' => $item->grammage,
                            ],
                            ['quantity' => 0]
                        );
                        $agentStock->increment('quantity', $accepted);
                    }
                }
            }

            $record->update([
                'status' => StockTransferStatus::Received,
                'received_by' => auth()->id(),
                'received_at' => now(),
                'to_warehouse_id' => $toWarehouseId ?? $record->to_warehouse_id,
                'to_agent_id' => $toAgentId ?? $record->to_agent_id,
            ]);
        });
    }
}
