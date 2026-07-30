<?php

namespace App\Filament\Resources\WarehouseReturns\Pages;

use App\Filament\Resources\WarehouseReturns\WarehouseReturnResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWarehouseReturns extends ListRecords
{
    protected static string $resource = WarehouseReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
