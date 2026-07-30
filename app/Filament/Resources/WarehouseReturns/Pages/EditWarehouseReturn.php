<?php

namespace App\Filament\Resources\WarehouseReturns\Pages;

use App\Filament\Resources\WarehouseReturns\WarehouseReturnResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWarehouseReturn extends EditRecord
{
    protected static string $resource = WarehouseReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
