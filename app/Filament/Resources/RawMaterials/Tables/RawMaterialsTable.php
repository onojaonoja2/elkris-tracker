<?php

namespace App\Filament\Resources\RawMaterials\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RawMaterialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('unit_of_measure')
                    ->label('Unit')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('reorder_level')
                    ->label('Reorder Level')
                    ->numeric(decimalPlaces: 4)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('stock_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn ($record): string => $record->reorder_level !== null && $record->quantity <= $record->reorder_level ? 'Low Stock' : 'OK')
                    ->color(fn (string $state): string => $state === 'Low Stock' ? 'danger' : 'success'),

                ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->filters([
                Filter::make('low_stock')
                    ->label('Low Stock')
                    ->query(fn (Builder $query) => $query
                        ->whereNotNull('reorder_level')
                        ->whereColumn('quantity', '<=', 'reorder_level')),

                Filter::make('active')
                    ->label('Active')
                    ->query(fn (Builder $query) => $query->where('is_active', true))
                    ->toggle(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
