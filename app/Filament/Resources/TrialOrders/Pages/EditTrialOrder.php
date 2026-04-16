<?php

namespace App\Filament\Resources\TrialOrders\Pages;

use App\Filament\Resources\TrialOrders\TrialOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrialOrder extends EditRecord
{
    protected static string $resource = TrialOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
