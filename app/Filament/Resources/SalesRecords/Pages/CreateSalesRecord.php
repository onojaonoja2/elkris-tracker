<?php

namespace App\Filament\Resources\SalesRecords\Pages;

use App\Filament\Resources\SalesRecords\SalesRecordResource;
use App\Models\AgentStock;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesRecord extends CreateRecord
{
    protected static string $resource = SalesRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['agent_id'] = auth()->id();
        $data['agent_type'] = auth()->user()->getPrimaryRole();

        $products = $data['products'] ?? [];

        foreach ($products as $product) {
            $productName = $product['product_name'] ?? null;
            $grammage = $product['grammage'] ?? null;
            $quantity = $product['quantity'] ?? 0;

            if ($productName && $grammage && $quantity > 0) {
                $agentStock = AgentStock::where('user_id', auth()->id())
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

        if (! empty($data['is_credit'])) {
            $data['status'] = 'pending';
            $data['credit_status'] = 'pending_payment';
        } else {
            $data['status'] = 'receipt_uploaded';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->dispatch('refresh-dashboard');
    }
}
