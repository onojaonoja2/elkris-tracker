<?php

namespace App\Filament\Resources\TrialOrders\Pages;

use App\Filament\Resources\TrialOrders\TrialOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrialOrder extends CreateRecord
{
    protected static string $resource = TrialOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Inherently attach the creating user's agent ID natively to the database push
        $data['agent_id'] = auth()->id();

        return $data;
    }
}
