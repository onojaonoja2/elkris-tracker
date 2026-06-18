<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class WarehouseManagerStockBreakdownWidget extends TableWidget
{
    protected static ?string $heading = 'Stock Breakdown';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->role === 'warehouse_manager';
    }

    public function table(Table $table): Table
    {
        $warehouseIds = auth()->user()->managedWarehouses()->pluck('id');

        return $table
            ->query(fn () => Inventory::whereIn('warehouse_id', $warehouseIds)->with('productType', 'warehouse'))
            ->columns([
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable(),
                TextColumn::make('productType.name')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('grammage')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state.'g'),
                TextColumn::make('carton_size')
                    ->label('Carton Size')
                    ->state(function (Inventory $record): ?string {
                        $productType = $record->productType;
                        if (! $productType || ! is_array($productType->available_grammages)) {
                            return null;
                        }
                        $entry = collect($productType->available_grammages)
                            ->first(fn ($g) => (is_array($g) ? $g['grammage'] : $g) == $record->grammage);

                        return is_array($entry) ? ($entry['carton_quantity'] ?? null).' pcs' : null;
                    }),
                TextColumn::make('quantity')
                    ->label('Total Pieces')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')),
                TextColumn::make('full_cartons')
                    ->label('Full Cartons')
                    ->state(function (Inventory $record): ?int {
                        $productType = $record->productType;
                        if (! $productType || ! is_array($productType->available_grammages)) {
                            return null;
                        }
                        $entry = collect($productType->available_grammages)
                            ->first(fn ($g) => (is_array($g) ? $g['grammage'] : $g) == $record->grammage);
                        $cartonQty = is_array($entry) ? ($entry['carton_quantity'] ?? null) : null;

                        return $cartonQty ? intdiv($record->quantity, $cartonQty) : null;
                    }),
                TextColumn::make('remaining_pieces')
                    ->label('Remaining')
                    ->state(function (Inventory $record): ?int {
                        $productType = $record->productType;
                        if (! $productType || ! is_array($productType->available_grammages)) {
                            return null;
                        }
                        $entry = collect($productType->available_grammages)
                            ->first(fn ($g) => (is_array($g) ? $g['grammage'] : $g) == $record->grammage);
                        $cartonQty = is_array($entry) ? ($entry['carton_quantity'] ?? null) : null;

                        return $cartonQty ? ($record->quantity % $cartonQty) : null;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
}
