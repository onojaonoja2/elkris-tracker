<?php

namespace App\Filament\Resources\ProductionRuns\Tables;

use App\Models\ProductionRun;
use App\Services\ProductionRunService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ProductionRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Run #')
                    ->sortable(),

                TextColumn::make('rawMaterials')
                    ->label('Raw Materials')
                    ->formatStateUsing(function (ProductionRun $record): string {
                        return $record->getMaterialsSummary()
                            ? collect($record->getMaterialsSummary())
                                ->map(fn ($m) => "{$m['name']}: {$m['quantity_used']} {$m['unit']}")
                                ->implode(', ')
                            : '-';
                    })
                    ->limit(50)
                    ->tooltip(function (ProductionRun $record): ?string {
                        $summary = $record->getMaterialsSummary();

                        return empty($summary) ? null : collect($summary)
                            ->map(fn ($m) => "{$m['name']}: {$m['quantity_used']} {$m['unit']}")
                            ->implode("\n");
                    }),

                TextColumn::make('total_quantity_used')
                    ->label('Total Used')
                    ->state(fn (ProductionRun $record): float => $record->getTotalQuantityUsed())
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
                    ->visible(fn (ProductionRun $record): bool => ! $record->isLocked())
                    ->mutateRecordDataUsing(function (array $data, ProductionRun $record): array {
                        $data['raw_materials'] = collect($record->getMaterialsSummary())
                            ->map(fn ($m) => [
                                'raw_material_id' => $m['raw_material_id'],
                                'quantity_used' => $m['quantity_used'],
                            ])
                            ->toArray();

                        return $data;
                    })
                    ->using(function (ProductionRun $record, array $data): ProductionRun {
                        try {
                            return ProductionRunService::update($record, $data);
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Update failed')
                                ->body($e->getMessage())
                                ->send();

                            throw $e;
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->hasAnyRole(['admin', 'production_management'])),
                ]),
            ]);
    }
}
