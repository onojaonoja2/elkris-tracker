<?php

namespace App\Filament\Resources\SalesRecords\Pages;

use App\Filament\Resources\SalesRecords\SalesRecordResource;
use App\Models\SalesRecord;
use App\Services\SalesRecordService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CreateSalesRecord extends CreateRecord
{
    protected static string $resource = SalesRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['agent_id'] = auth()->id();
        $data['agent_type'] = auth()->user()->getPrimaryRole();

        return $data;
    }

    protected function handleRecordCreation(array $data): SalesRecord
    {
        try {
            return SalesRecordService::submitSale($data);
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('Sales record could not be created')
                ->body(implode(' ', Arr::flatten($e->errors())))
                ->send();

            throw $e;
        }
    }

    protected function afterCreate(): void
    {
        $this->dispatch('refresh-dashboard');
    }

    protected function getCreatedNotification(): ?Notification
    {
        $warehouseFulfilled = $this->record->requiresWarehouseAllocation();

        return Notification::make()
            ->success()
            ->title('Sales record submitted successfully')
            ->body($warehouseFulfilled
                ? 'Stock request submitted and will be fulfilled from the warehouse upon approval.'
                : 'Stock deducted and record is pending verification.');
    }
}
