<?php

namespace App\Filament\Resources\SalesRecords\Pages;

use App\Filament\Resources\SalesRecords\SalesRecordResource;
use App\Models\SalesRecord;
use App\Services\SalesRecordService;
use Filament\Resources\Pages\CreateRecord;

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
        return SalesRecordService::submitSale($data);
    }

    protected function afterCreate(): void
    {
        $this->dispatch('refresh-dashboard');
    }
}
