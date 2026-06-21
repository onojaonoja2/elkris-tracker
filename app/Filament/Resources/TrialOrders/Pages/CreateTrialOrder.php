<?php

namespace App\Filament\Resources\TrialOrders\Pages;

use App\Enums\TrialOrderStatus;
use App\Filament\Resources\TrialOrders\TrialOrderResource;
use App\Models\AgentStock;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTrialOrder extends CreateRecord
{
    protected static string $resource = TrialOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $data['agent_id'] = $user->id;

        $products = $data['products'] ?? [];

        foreach ($products as $product) {
            $productName = $product['product_name'] ?? null;
            $grammage = $product['grammage'] ?? null;
            $quantity = $product['quantity'] ?? 0;

            if ($productName && $grammage && $quantity > 0) {
                $agentStock = AgentStock::where('user_id', $user->id)
                    ->where('product_name', $productName)
                    ->where('grammage', $grammage)
                    ->first();

                if (! $agentStock || $agentStock->quantity < $quantity) {
                    Notification::make()
                        ->danger()
                        ->title('Insufficient stock')
                        ->body("You don't have enough {$productName} ({$grammage}g) in your stock. Available: ".($agentStock?->quantity ?? 0))
                        ->send();

                    $this->halt();

                    return $data;
                }
            }
        }

        $data['status'] = TrialOrderStatus::ReceiptUploaded;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->dispatch('refresh-dashboard');
    }
}
