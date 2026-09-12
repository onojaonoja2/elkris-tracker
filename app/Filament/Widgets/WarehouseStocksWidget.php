<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class WarehouseStocksWidget extends TableWidget
{
    protected static ?string $heading = 'Available Stock — All Warehouses';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('warehouse_manager') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Inventory::query()
                ->with(['productType', 'warehouse'])
                ->where('quantity', '>', 0))
            ->columns([
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('productType.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('grammage')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state.'g'),
                TextColumn::make('carton_size')
                    ->label('Carton Size')
                    ->state(fn (Inventory $record): ?string => $this->cartonBreakdown($record)['carton_size']),
                TextColumn::make('quantity')
                    ->label('Total Pieces')
                    ->sortable(),
                TextColumn::make('full_cartons')
                    ->label('Full Cartons')
                    ->state(fn (Inventory $record): ?int => $this->cartonBreakdown($record)['cartons']),
                TextColumn::make('remaining_pieces')
                    ->label('Remaining')
                    ->state(fn (Inventory $record): ?int => $this->cartonBreakdown($record)['remaining']),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): Collection => Warehouse::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, int $warehouseId): Builder => $query->where('warehouse_id', $warehouseId),
                    )),
            ])
            ->recordActions([
                Action::make('viewMovements')
                    ->label('View Movements')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(fn (Inventory $record) => $this->dispatch(
                        'open-stock-movement-breakdown',
                        entityType: 'warehouse',
                        entityId: $record->warehouse_id,
                        product: $record->productType?->name,
                        grammage: $record->grammage,
                    )),
            ])
            ->defaultSort('warehouse.name')
            ->paginated([10, 25, 50, -1]);
    }

    /**
     * @return array{carton_size: ?string, cartons: ?int, remaining: ?int}
     */
    protected function cartonBreakdown(Inventory $record): array
    {
        $productType = $record->productType;

        if (! $productType || ! is_array($productType->available_grammages)) {
            return ['carton_size' => null, 'cartons' => null, 'remaining' => null];
        }

        $entry = collect($productType->available_grammages)
            ->first(fn ($g) => (is_array($g) ? $g['grammage'] : $g) == $record->grammage);

        $cartonQty = is_array($entry) ? ($entry['carton_quantity'] ?? null) : null;

        if (! $cartonQty) {
            return ['carton_size' => null, 'cartons' => null, 'remaining' => null];
        }

        return [
            'carton_size' => $cartonQty.' pcs',
            'cartons' => intdiv($record->quantity, $cartonQty),
            'remaining' => $record->quantity % $cartonQty,
        ];
    }
}
