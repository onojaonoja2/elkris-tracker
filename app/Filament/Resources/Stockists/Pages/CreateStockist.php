<?php

namespace App\Filament\Resources\Stockists\Pages;

use App\Filament\Resources\Stockists\StockistResource;
use App\Models\User;
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

    protected function afterCreate(): void
    {
        $stockist = $this->record;

        User::create([
            'name' => $stockist->name,
            'email' => $this->data['email'],
            'password' => $this->data['password'],
            'role' => 'stockist',
            'stockist_id' => $stockist->id,
            'phone' => $stockist->phone,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
