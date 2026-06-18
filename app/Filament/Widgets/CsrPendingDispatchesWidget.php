<?php

namespace App\Filament\Widgets;

use App\Models\StockTransfer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CsrPendingDispatchesWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Pending Dispatches';

    public function table(Table $table): Table
    {
        return $table
            ->query(StockTransfer::where('to_agent_id', auth()->id())
                ->whereIn('status', ['requested', 'approved', 'dispatched']))
            ->columns([
                TextColumn::make('id')
                    ->label('Transfer #'),
                TextColumn::make('fromWarehouse.name')
                    ->label('From')
                    ->state(fn ($record): ?string => $record->fromWarehouse?->name ?? $record->fromAgent?->name),
                TextColumn::make('items')
                    ->label('Items')
                    ->formatStateUsing(fn ($record): string => $record->items->map(
                        fn ($item) => ($item->productType?->name ?? '').' x'.$item->quantity
                    )->implode(', '))
                    ->limit(50),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
