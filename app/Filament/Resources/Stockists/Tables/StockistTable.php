<?php

namespace App\Filament\Resources\Stockists\Tables;

use App\Filament\Exports\StockistExporter;
use App\Models\Stockist;
use App\Models\StockistTransaction;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockistTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->searchable(),

                TextColumn::make('city')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('state')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('region')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('stock_balance')
                    ->label('Stock Value (₦)')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('total_units')
                    ->label('Total Units')
                    ->state(fn ($record) => $record->stocks()->sum('quantity'))
                    ->sortable(false),

                TextColumn::make('last_received_date')
                    ->label('Last Received Date')
                    ->state(function ($record) {
                        $lastTx = StockistTransaction::where('stockist_id', $record->id)
                            ->where('type', 'received')
                            ->latest('transaction_date')
                            ->first();

                        return $lastTx ? $lastTx->transaction_date->format('d/m/Y') : 'N/A';
                    })
                    ->sortable(false),

                TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('region')
                    ->options(fn () => Stockist::distinct()->pluck('region', 'region')->filter()->toArray())
                    ->searchable(),
                SelectFilter::make('state')
                    ->options(fn () => Stockist::distinct()->pluck('state', 'state')->filter()->toArray())
                    ->searchable(),
                SelectFilter::make('city')
                    ->options(fn () => Stockist::distinct()->pluck('city', 'city')->filter()->toArray())
                    ->searchable(),
                SelectFilter::make('last_received_date')
                    ->label('Stock Received Date')
                    ->options([
                        'today' => 'Today',
                        'this_week' => 'This Week',
                        'this_month' => 'This Month',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'today' => $query->whereHas('transactions', function ($q) {
                                $q->where('type', 'received')->whereDate('transaction_date', today());
                            }),
                            'this_week' => $query->whereHas('transactions', function ($q) {
                                $q->where('type', 'received')->whereBetween('transaction_date', [now()->startOfWeek(), now()->endOfWeek()]);
                            }),
                            'this_month' => $query->whereHas('transactions', function ($q) {
                                $q->where('type', 'received')->whereBetween('transaction_date', [now()->startOfMonth(), now()->endOfMonth()]);
                            }),
                            default => $query,
                        };
                    }),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(StockistExporter::class),
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
