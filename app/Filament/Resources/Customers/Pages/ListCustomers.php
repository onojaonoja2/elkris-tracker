<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->hidden(fn () => in_array(auth()->user()->role, ['sales', 'accountant', 'warehouse_manager'])),
            Action::make('import')
                ->label('Import Customers')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->visible(fn () => in_array(auth()->user()->role, ['admin', 'manager', 'lead', 'rep']))
                ->url(CustomerResource::getUrl('import')),
        ];
    }
}
