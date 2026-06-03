<?php

namespace App\Filament\Resources\Stockists\Pages;

use App\Filament\Resources\Stockists\StockistResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class ViewStockist extends ViewRecord
{
    protected static string $resource = StockistResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Contact Information')
                    ->columns(2)
                    ->schema([
                        Text::make(fn ($record) => 'Name: '.$record->name),
                        Text::make(fn ($record) => 'Phone: '.$record->phone),
                        Text::make(fn ($record) => 'Address: '.$record->address),
                        Text::make(fn ($record) => 'City: '.$record->city),
                        Text::make(fn ($record) => 'State: '.optional($record->stateRelation)->name),
                        Text::make(fn ($record) => 'Region: '.$record->region),
                    ]),
                Section::make('Stock Overview')
                    ->columns(2)
                    ->schema([
                        Text::make(fn ($record) => 'Stock Value: ₦'.number_format((float) $record->stock_balance, 2)),
                        Text::make(fn ($record) => $record->stocks()->count().' products in stock'),
                    ]),
            ]);
    }
}
