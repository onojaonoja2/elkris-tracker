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

                TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->color(fn (bool $state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No')
                    ->visible(fn () => auth()->user()->role !== 'admin'),

                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->visible(fn () => auth()->user()->role === 'admin'),
            ])
            ->filters([
                //
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => auth()->user()->role === 'admin'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->role === 'admin'),
                ]),
            ]);
    }
}
