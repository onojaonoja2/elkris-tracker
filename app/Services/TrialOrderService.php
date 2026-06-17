<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\TrialOrderStatus;
use App\Models\AgentStock;
use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
use App\Models\TrialOrder;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class TrialOrderService
{
    public function approveByAccountant(TrialOrder $record, ?string $notes): ?Notification
    {
        $products = $record->products ?? [];

        DB::transaction(function () use ($record, $products, $notes) {
            foreach ($products as $product) {
                $productName = $product['product_name'] ?? null;
                $grammage = $product['grammage'] ?? null;
                $quantity = $product['quantity'] ?? 0;

                if (! $productName || ! $grammage || $quantity <= 0) {
                    continue;
                }

                if ($record->stockist_id) {
                    $stockistStock = StockistStock::where('stockist_id', $record->stockist_id)
                        ->where('product_name', $productName)
                        ->where('grammage', $grammage)
                        ->lockForUpdate()
                        ->first();

                    if (! $stockistStock || $stockistStock->quantity < $quantity) {
                        return Notification::make()
                            ->danger()
                            ->title('Insufficient stock')
                            ->body("Stockist doesn't have enough {$productName} ({$grammage}g). Available: ".($stockistStock->quantity ?? 0));
                    }

                    $stockistStock->decrement('quantity', $quantity);
                }

                if ($record->agent_id) {
                    $agentStock = AgentStock::where('user_id', $record->agent_id)
                        ->where('product_name', $productName)
                        ->where('grammage', $grammage)
                        ->lockForUpdate()
                        ->first();

                    if (! $agentStock || $agentStock->quantity < $quantity) {
                        return Notification::make()
                            ->danger()
                            ->title('Insufficient agent stock')
                            ->body("Agent doesn't have enough {$productName} ({$grammage}g). Available: ".($agentStock->quantity ?? 0));
                    }

                    $agentStock->decrement('quantity', $quantity);
                }
            }

            if ($record->stockist_id) {
                StockistTransaction::create([
                    'stockist_id' => $record->stockist_id,
                    'user_id' => auth()->id(),
                    'field_agent_id' => $record->agent_id,
                    'trial_order_id' => $record->id,
                    'type' => 'deducted',
                    'amount' => $record->total_value,
                    'description' => "Auto-deducted for trial order #{$record->id}",
                    'transaction_date' => now()->toDateString(),
                ]);

                $record->stockist?->decrement('stock_balance', $record->total_value);
            }

            if ($record->agent_id) {
                $record->agent?->increment('stock_balance', $record->total_value);
            }

            $record->update([
                'status' => TrialOrderStatus::Approved,
                'accountant_verified_at' => now(),
                'accountant_verified_by' => auth()->id(),
                'accountant_notes' => $notes,
                'payment_status' => PaymentStatus::Completed,
            ]);
        });

        Notification::make()->title('Trial order approved and stock deducted')->success()->send();

        return null;
    }

    public function rejectByAccountant(TrialOrder $record, string $reason): void
    {
        $record->update([
            'status' => TrialOrderStatus::Rejected,
            'accountant_verified_at' => now(),
            'accountant_verified_by' => auth()->id(),
            'accountant_notes' => $reason,
        ]);

        Notification::make()->title('Trial order rejected')->danger()->send();
    }

    public function processPayment(TrialOrder $record, array $data): ?Notification
    {
        $balanceHolder = $data['balance_holder'] ?? 'agent';
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $selectedStockistId = $data['stockist_id'] ?? null;

        $agent = $record->agent;
        $products = $record->products ?? [];

        $stockist = null;

        if ($balanceHolder === 'stockist' && $selectedStockistId) {
            $stockist = Stockist::find($selectedStockistId);
        }

        if (! $stockist && $balanceHolder === 'agent') {
            $stockist = Stockist::where('supervisor_id', auth()->id())
                ->whereIn('city', (array) ($agent?->assigned_cities ?? []))
                ->first();
        }

        if (! $stockist) {
            return Notification::make()
                ->danger()
                ->title('No stockist found')
                ->body('No stockist found with available stock. Please select a stockist with sufficient inventory.');
        }

        foreach ($products as $product) {
            $productName = $product['product_name'] ?? null;
            $grammage = $product['grammage'] ?? null;
            $quantity = $product['quantity'] ?? 0;

            if ($productName && $grammage && $quantity > 0) {
                $stock = StockistStock::where('stockist_id', $stockist->id)
                    ->where('product_name', $productName)
                    ->where('grammage', $grammage)
                    ->first();

                if (! $stock || $stock->quantity < $quantity) {
                    return Notification::make()
                        ->danger()
                        ->title('Insufficient stock')
                        ->body("Insufficient stock for {$productName} ({$grammage}g). Available: ".($stock->quantity ?? 0).", Requested: {$quantity}");
                }
            }
        }

        DB::transaction(function () use ($stockist, $products, $record, $balanceHolder, $paymentMethod, $agent) {
            foreach ($products as $product) {
                $productName = $product['product_name'] ?? null;
                $grammage = $product['grammage'] ?? null;
                $quantity = $product['quantity'] ?? 0;

                if ($productName && $grammage && $quantity > 0) {
                    $stockistStock = StockistStock::firstOrCreate(
                        [
                            'stockist_id' => $stockist->id,
                            'product_name' => $productName,
                            'grammage' => $grammage,
                        ],
                        ['quantity' => 0]
                    );

                    $stockistStock = StockistStock::where('id', $stockistStock->id)
                        ->lockForUpdate()
                        ->first();

                    if ($stockistStock->quantity < $quantity) {
                        throw new \Exception("Insufficient stock: {$productName} ({$grammage}g). Available: {$stockistStock->quantity}, Requested: {$quantity}");
                    }

                    $stockistStock->decrement('quantity', $quantity);
                }
            }

            $updateData = [
                'payment_status' => PaymentStatus::Completed,
                'status' => TrialOrderStatus::Approved,
                'approved_by' => auth()->id(),
                'stockist_id' => $stockist->id,
            ];

            if ($balanceHolder === 'agent' && $agent) {
                $updateData['agent_balance'] = $record->total_value;
                $updateData['stockist_balance'] = 0;
                $agent->increment('stock_balance', $record->total_value);
            } else {
                $updateData['agent_balance'] = 0;
                $updateData['stockist_balance'] = $record->total_value;
                $stockist->decrement('stock_balance', $record->total_value);
            }

            $record->update($updateData);

            StockistTransaction::create([
                'stockist_id' => $stockist->id,
                'user_id' => auth()->id(),
                'field_agent_id' => $agent?->id,
                'trial_order_id' => $record->id,
                'type' => 'deducted',
                'amount' => $record->total_value,
                'description' => "Trial order approved - Payment via {$paymentMethod}, Balance held with {$balanceHolder}",
                'transaction_date' => now()->toDateString(),
            ]);
        });

        return null;
    }

    public function attributeSale(TrialOrder $record): void
    {
        $products = $record->products ?? [];

        DB::transaction(function () use ($record, $products) {
            if ($record->agent_id) {
                $agent = $record->agent;
                if ($agent) {
                    $agent->increment('stock_balance', $record->total_value);
                }

                StockistTransaction::create([
                    'user_id' => auth()->id(),
                    'field_agent_id' => $record->agent_id,
                    'trial_order_id' => $record->id,
                    'type' => 'deducted',
                    'amount' => $record->total_value,
                    'description' => 'Trial order sale attributed - supervisor approved',
                    'transaction_date' => now()->toDateString(),
                ]);
            }

            if ($record->stockist_id) {
                foreach ($products as $product) {
                    $productName = $product['product_name'] ?? null;
                    $grammage = $product['grammage'] ?? null;
                    $quantity = $product['quantity'] ?? 0;

                    if ($productName && $grammage && $quantity > 0) {
                        $stock = StockistStock::where('stockist_id', $record->stockist_id)
                            ->where('product_name', $productName)
                            ->where('grammage', $grammage)
                            ->first();

                        if ($stock && $stock->quantity >= $quantity) {
                            $stock->decrement('quantity', $quantity);
                        }
                    }
                }

                $record->stockist?->decrement('stock_balance', $record->total_value);
            }

            $record->update([
                'payment_status' => PaymentStatus::Completed,
            ]);
        });
    }
}
