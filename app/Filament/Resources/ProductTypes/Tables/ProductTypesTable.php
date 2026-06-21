<?php

namespace App\Filament\Resources\ProductTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ProductTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('available_grammages_formatted')
                    ->label('Available Weights')
                    ->state(fn (mixed $record): string => collect($record->available_grammages)
                        ->map(fn ($g) => is_array($g) ? ($g['grammage'] ?? $g) : $g)
                        ->map(fn ($g) => $g.'g')
                        ->implode(', ')),

                ToggleColumn::make('is_active')
                    ->label('Active'),

                TextColumn::make('products_count')
                    ->label('In Use')
                    ->counts('products'),
            ])
            ->filters([
                //
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
