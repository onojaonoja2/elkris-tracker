<?php

namespace App\Filament\Resources\PortfolioResource\Pages;

use App\Filament\Resources\PortfolioResource;
use Filament\Resources\Pages\ListRecords;

class ListPortfolios extends ListRecords
{
    protected static string $resource = PortfolioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No direct creating inside the portfolio (customers flow organically here after acceptance)
        ];
    }
}
