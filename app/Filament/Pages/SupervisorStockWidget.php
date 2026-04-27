<?php

namespace App\Filament\Pages;

use App\Models\Stockist;
use App\Models\StockistStock;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class SupervisorStockWidget extends TableWidget
{
    protected static ?string $heading = 'Stock Breakdown by Product';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $stockistIds = Stockist::where('supervisor_id', $user->id)->pluck('id');

        return $table
            ->query(
                fn () => StockistStock::whereIn('stockist_id', $stockistIds)
                    ->orderBy('product_name')
                    ->orderBy('grammage')
            )
            ->columns([
                TextColumn::make('stockist.name')
                    ->label('Stockist')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product_name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('grammage')
                    ->label('Grammage')
                    ->formatStateUsing(fn ($state) => "{$state}g")
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
            ])
            ->filters([
                //
            ]);
    }
}
