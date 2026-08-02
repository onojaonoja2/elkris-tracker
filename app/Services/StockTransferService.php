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
            $record->load('items.productType');

            foreach ($items as $itemData) {
                $item = $record->items->firstWhere('id', $itemData['item_id']);
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
                        Inventory::firstOrCreate(
                            [
                                'warehouse_id' => $record->to_warehouse_id,
                                'product_type_id' => $item->product_type_id,
                                'grammage' => $item->grammage,
                            ],
                            ['quantity' => 0]
                        )->increment('quantity', $accepted);
                    }

                    if ($record->to_agent_id) {
                        AgentStock::firstOrCreate(
                            [
                                'user_id' => $record->to_agent_id,
                                'product_type_id' => $item->product_type_id,
                                'product_name' => $item->productType?->name ?? 'Unknown',
                                'grammage' => $item->grammage,
                            ],
                            ['quantity' => 0]
                        )->increment('quantity', $accepted);
                    }
                }
            }

            if ($record->from_warehouse_id) {
                $inventories = Inventory::where(function ($q) use ($record) {
                    foreach ($record->items as $item) {
                        $q->orWhere(function ($sub) use ($record, $item) {
                            $sub->where('warehouse_id', $record->from_warehouse_id)
                                ->where('product_type_id', $item->product_type_id)
                                ->where('grammage', $item->grammage);
                        });
                    }
                })->get()->keyBy(fn ($inv) => "{$inv->warehouse_id}-{$inv->product_type_id}-{$inv->grammage}");

                foreach ($record->items as $item) {
                    $key = "{$record->from_warehouse_id}-{$item->product_type_id}-{$item->grammage}";
                    if (isset($inventories[$key])) {
                        $deductQty = max(0, $item->quantity - $item->rejected_quantity);
                        $inventories[$key]->decrement('quantity', $deductQty);
                    }
                }
            }

            if ($record->from_agent_id) {
                $agentStocks = AgentStock::where('user_id', $record->from_agent_id)
                    ->whereIn('grammage', $record->items->pluck('grammage'))
                    ->get()->keyBy(fn ($s) => "{$s->user_id}-{$s->product_type_id}-{$s->grammage}");

                foreach ($record->items as $item) {
                    $key = "{$record->from_agent_id}-{$item->product_type_id}-{$item->grammage}";
                    $stock = $agentStocks[$key] ?? AgentStock::where('user_id', $record->from_agent_id)
                        ->where('product_name', $item->productType?->name ?? 'Unknown')
                        ->where('grammage', $item->grammage)
                        ->first();

                    if ($stock) {
                        $deductQty = max(0, $item->quantity - $item->rejected_quantity);
                        $stock->decrement('quantity', $deductQty);
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

                $record->load('items.productType');

                $inventories = Inventory::where(function ($q) use ($record) {
                    foreach ($record->items as $item) {
                        $q->orWhere(function ($sub) use ($record, $item) {
                            $sub->where('warehouse_id', $record->from_warehouse_id)
                                ->where('product_type_id', $item->product_type_id)
                                ->where('grammage', $item->grammage);
                        });
                    }
                })->get()->keyBy(fn ($inv) => "{$inv->warehouse_id}-{$inv->product_type_id}-{$inv->grammage}");

                foreach ($record->items as $item) {
                    $key = "{$record->from_warehouse_id}-{$item->product_type_id}-{$item->grammage}";
                    $available = isset($inventories[$key]) ? $inventories[$key]->quantity : 0;

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

                $productName = $itemData['product_name'] ?? ($record->items()->first()?->productType?->name ?? 'Unknown');
                $agentStock = AgentStock::where([
                    'user_id' => $record->from_agent_id,
                    'product_name' => $productName,
                    'grammage' => $itemData['grammage'],
                ])->first();

                if ($agentStock) {
                    $agentStock->decrement('quantity', $itemData['quantity']);
                }
            }
        });
    }

    public static function reassign(StockTransfer $record, array $data): void
    {
        DB::transaction(function () use ($record, $data) {
            $record->load('items.productType');
            $toWarehouseId = $data['to_warehouse_id'] ?? null;
            $toAgentId = $data['to_agent_id'] ?? null;

            if ($record->from_agent_id && $record->status !== StockTransferStatus::Collected) {
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
                        Inventory::firstOrCreate(
                            [
                                'warehouse_id' => $toWarehouseId,
                                'product_type_id' => $item->product_type_id,
                                'grammage' => $item->grammage,
                            ],
                            ['quantity' => 0]
                        )->increment('quantity', $accepted);

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
                        AgentStock::firstOrCreate(
                            [
                                'user_id' => $toAgentId,
                                'product_type_id' => $item->product_type_id,
                                'product_name' => $item->productType?->name ?? 'Unknown',
                                'grammage' => $item->grammage,
                            ],
                            ['quantity' => 0]
                        )->increment('quantity', $accepted);
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
