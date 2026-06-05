<?php

namespace App\Filament\Resources\Warehouses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => $state === 'central' ? 'warning' : 'info'),

                TextColumn::make('state.name')
                    ->label('State'),

                TextColumn::make('lga.name')
                    ->label('LGA'),

                TextColumn::make('manager.name')
                    ->label('Manager'),

                TextColumn::make('salesPerson.name')
                    ->label('Sales Person'),

                ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->filters([
                //
            ])
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
