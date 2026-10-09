<?php

namespace App\Filament\Resources\ProductTypes\Tables;

use App\Models\ProductType;
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
                    ->state(function (mixed $record): string {
                        return collect($record->available_grammages)
                            ->map(function ($entry) {
                                if (! is_array($entry)) {
                                    return $entry.'g';
                                }

                                $weight = ProductType::formatGrammageEntry($entry);
                                $perPackage = (int) ($entry['carton_quantity'] ?? 1);
                                $packaging = ($entry['packaging'] ?? 'carton') === 'bag' ? 'bag' : 'carton';

                                return $weight.' • '.$perPackage.' pcs/'.$packaging;
                            })
                            ->implode(', ');
                    }),

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
