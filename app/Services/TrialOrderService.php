<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\TrialOrderStatus;
use App\Models\AgentStock;
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

    public function attributeSale(TrialOrder $record): void
    {
        $products = $record->products ?? [];

        DB::transaction(function () use ($record, $products) {
            if ($record->agent_id) {
                $agent = $record->agent;
                if ($agent) {
                    $agent->increment('stock_balance', $record->total_value);
                }

                foreach ($products as $product) {
                    $productName = $product['product_name'] ?? null;
                    $grammage = $product['grammage'] ?? null;
                    $quantity = $product['quantity'] ?? 0;

                    if ($productName && $grammage && $quantity > 0) {
                        $stock = AgentStock::where('user_id', $record->agent_id)
                            ->where('product_name', $productName)
                            ->where('grammage', $grammage)
                            ->first();

                        if ($stock && $stock->quantity >= $quantity) {
                            $stock->decrement('quantity', $quantity);
                        }
                    }
                }
            }

            $record->update([
                'payment_status' => PaymentStatus::Completed,
            ]);
        });
    }
}
