<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use App\Models\ProductType;
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
        return auth()->user()->hasRole('warehouse_manager');
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
                    ->getStateUsing(fn (Inventory $record): string => ProductType::formatGrammage($record->productType, $record->grammage)),
                TextColumn::make('carton_size')
                    ->label('Package Size')
                    ->state(function (Inventory $record): ?string {
                        $entry = $record->productType?->grammageEntryFor((int) $record->grammage);

                        if (! $entry) {
                            return null;
                        }

                        $package = ($entry['packaging'] ?? 'carton') === 'bag' ? 'bag' : 'carton';

                        return $entry['carton_quantity'].' pcs/'.$package;
                    }),
                TextColumn::make('quantity')
                    ->label('Total Pieces')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')),
                TextColumn::make('full_cartons')
                    ->label('Full Packages')
                    ->state(function (Inventory $record): ?int {
                        $cartonQty = $record->productType?->grammageEntryFor((int) $record->grammage)['carton_quantity'] ?? null;

                        return $cartonQty ? intdiv($record->quantity, $cartonQty) : null;
                    }),
                TextColumn::make('remaining_pieces')
                    ->label('Remaining')
                    ->state(function (Inventory $record): ?int {
                        $cartonQty = $record->productType?->grammageEntryFor((int) $record->grammage)['carton_quantity'] ?? null;

                        return $cartonQty ? ($record->quantity % $cartonQty) : null;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
}
