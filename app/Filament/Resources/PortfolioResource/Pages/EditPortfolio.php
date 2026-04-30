<?php

namespace App\Filament\Resources\PortfolioResource\Pages;

use App\Filament\Resources\PortfolioResource;
use Filament\Resources\Pages\EditRecord;

class EditPortfolio extends EditRecord
{
    protected static string $resource = PortfolioResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
