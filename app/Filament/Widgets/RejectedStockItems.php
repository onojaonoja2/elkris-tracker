<?php

namespace App\Filament\Widgets;

use App\Models\StockTransfer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RejectedStockItems extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'warehouse_manager']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockTransfer::whereHas('items', fn ($q) => $q->where('rejected_quantity', '>', 0)->whereNull('rejection_resolved_at'))
                    ->with(['items' => fn ($q) => $q->where('rejected_quantity', '>', 0)->whereNull('rejection_resolved_at'), 'fromWarehouse', 'toWarehouse', 'toStockist'])
            )
            ->columns([
                TextColumn::make('id')
                    ->label('Transfer #'),
                TextColumn::make('fromWarehouse.name')
                    ->label('From Warehouse'),
                TextColumn::make('toWarehouse.name')
                    ->label('To Warehouse'),
                TextColumn::make('toStockist.name')
                    ->label('To Stockist'),
                TextColumn::make('rejected_details')
                    ->label('Rejected Items')
                    ->state(fn (StockTransfer $record): string => $record->items->map(
                        fn ($item) => ($item->productType?->name ?? '')." {$item->grammage}g x{$item->rejected_quantity}"
                            .($item->rejection_reason ? " ({$item->rejection_reason})" : '')
                    )->implode('; ')),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
