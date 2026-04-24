<?php

namespace App\Filament\Resources\Stockists\Pages;

use App\Filament\Resources\Stockists\StockistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStockist extends CreateRecord
{
    protected static string $resource = StockistResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $data['created_by'] = $user->id;
        $data['supervisor_id'] = $user->id;

        return $data;
    }
}
