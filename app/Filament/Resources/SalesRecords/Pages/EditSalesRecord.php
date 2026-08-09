<?php

namespace App\Filament\Resources\SalesRecords\Pages;

use App\Filament\Resources\SalesRecords\SalesRecordResource;
use App\Models\ProductType;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesRecord extends EditRecord
{
    protected static string $resource = SalesRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => ! $this->getRecord()->isLocked()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['products'] = array_map(function (array $product): array {
            if (array_key_exists('cartons', $product) && array_key_exists('pieces', $product)) {
                return $product;
            }

            $productType = ProductType::where('name', $product['product_name'] ?? null)->first();
            $cartonQuantity = $productType?->cartonQuantityFor((int) ($product['grammage'] ?? 0)) ?? 1;
            $quantity = (int) ($product['quantity'] ?? 0);

            $product['cartons'] = intdiv($quantity, $cartonQuantity);
            $product['pieces'] = $quantity % $cartonQuantity;

            return $product;
        }, $data['products'] ?? []);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['is_credit'] ?? false) && blank($this->record->credit_status)) {
            $data['credit_status'] = 'pending_payment';
        }

        if (empty($data['is_credit'])) {
            $data['credit_status'] = null;
            $data['expected_collection_date'] = null;
        }

        if (! in_array($this->record->status, ['approved', 'rejected'], true)) {
            $data['status'] = 'pending';
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->dispatch('refresh-dashboard');
    }

    public function getFormActions(): array
    {
        if ($this->getRecord()->isLocked()) {
            return [];
        }

        return parent::getFormActions();
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if ($this->getRecord()->isLocked()) {
            throw new \Exception('Cannot modify a locked sales record.');
        }

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    protected function getRedirectUrl(): ?string
    {
        if ($this->getRecord()->isLocked()) {
            return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()->getKey()]);
        }

        return parent::getRedirectUrl();
    }
}
