<?php

namespace App\Filament\Resources\ProductionRuns\Tables;

use App\Models\ProductionRun;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductionRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Run #')
                    ->sortable(),

                TextColumn::make('rawMaterial.name')
                    ->label('Raw Material')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quantity_used')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('production_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('output_name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('output_quantity')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('output_unit')
                    ->label('Unit'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'reviewed' => 'success',
                        'flagged' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('creator.name')
                    ->label('Recorded By')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('accountantReviewer.name')
                    ->label('Reviewed By')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Pending'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending_review' => 'Pending Review',
                        'reviewed' => 'Reviewed',
                        'flagged' => 'Flagged',
                    ])
                    ->native(false),

                Filter::make('production_date')
                    ->label('This Month')
                    ->query(fn (Builder $query) => $query->whereMonth('production_date', now()->month)->whereYear('production_date', now()->year))
                    ->toggle(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->visible(fn (ProductionRun $record): bool => ! $record->isLocked()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->hasAnyRole(['admin', 'production_management'])),
                ]),
            ]);
    }
}
