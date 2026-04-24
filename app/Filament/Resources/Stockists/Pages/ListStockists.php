<?php

namespace App\Filament\Resources\Stockists\Pages;

use App\Filament\Resources\Stockists\StockistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockists extends ListRecords
{
    protected static string $resource = StockistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
