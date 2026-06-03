<?php

namespace App\Filament\Widgets;

use App\Models\Stockist;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class NationalStockistOverview extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, [
            'admin', 'supervisor', 'manager', 'lead', 'rep', 'accountant', 'sales',
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Stockist::query()
                    ->with(['stocks', 'supervisor', 'stateRelation'])
                    ->withCount(['stocks as total_units' => fn ($q) => $q->selectRaw('COALESCE(SUM(quantity), 0)')])
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->searchable(),

                TextColumn::make('city')
                    ->label('City')
                    ->searchable(),

                TextColumn::make('stateRelation.name')
                    ->label('State')
                    ->sortable(),

                TextColumn::make('region')
                    ->label('Region'),

                TextColumn::make('stock_balance')
                    ->label('Stock Value (₦)')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('total_units')
                    ->label('Total Units')
                    ->sortable(),

                TextColumn::make('supervisor.name')
                    ->label('Supervisor'),
            ])
            ->defaultSort('name');
    }
}
