<?php

namespace App\Filament\Widgets;

use App\Models\ProductType;
use App\Models\Repack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Livewire\Attributes\On;

class WarehouseRepacksWidget extends BaseWidget
{
    protected static ?string $heading = 'Stock Repacks';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['warehouse_manager', 'admin', 'manager']) || (auth()->user()?->managesWarehouses() ?? false);
    }

    public function table(Table $table): Table
    {
        $warehouseIds = (auth()->user()?->canManageWarehouses() && ! auth()->user()->hasAnyRole(['admin', 'manager']))
            ? auth()->user()->managedWarehouses()->pluck('id')
            : null;

        return $table
            ->query(fn () => Repack::query()
                ->when($warehouseIds, fn ($query) => $query->whereIn('warehouse_id', $warehouseIds))
                ->with(['warehouse', 'user', 'inputProductType', 'outputProductType'])
                ->orderBy('created_at', 'desc'))
            ->columns([
                TextColumn::make('id')
                    ->label('Repack #')
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse'),
                TextColumn::make('input')
                    ->label('From')
                    ->state(fn (Repack $record): string => "{$record->input_quantity}x {$record->inputProductType?->name} (".ProductType::formatGrammage($record->inputProductType, $record->input_grammage).')'),
                TextColumn::make('output')
                    ->label('To')
                    ->state(fn (Repack $record): string => "{$record->output_quantity}x {$record->outputProductType?->name} (".ProductType::formatGrammage($record->outputProductType, $record->output_grammage).')'),
                TextColumn::make('user.name')
                    ->label('Posted By')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->paginated([10, 25]);
    }
}
