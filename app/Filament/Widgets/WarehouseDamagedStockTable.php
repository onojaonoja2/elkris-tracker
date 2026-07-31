<?php

namespace App\Filament\Widgets;

use App\Models\DamagedInventory;
use App\Support\DashboardDateScope;
use App\Support\WarehouseOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class WarehouseDamagedStockTable extends TableWidget
{
    protected static ?string $heading = 'Damaged Stock Inventory';

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

        $excludeCurrentWarehouseOptions = fn (int $currentWarehouseId): array => array_filter(
            WarehouseOptions::for(auth()->user()),
            fn (string $name, int|string $id): bool => (int) $id !== $currentWarehouseId,
            ARRAY_FILTER_USE_BOTH
        );

        return $table
            ->query(
                fn (): Builder => DashboardDateScope::scope(
                    DamagedInventory::where(function (Builder $query) use ($warehouseIds) {
                        $query->whereIn('warehouse_id', $warehouseIds)
                            ->orWhereIn('destination_warehouse_id', $warehouseIds);
                    })->orderBy('created_at', 'desc'),
                    'created_at'
                )
            )
            ->columns([
                TextColumn::make('damagedStockReturn.id')
                    ->label('Return #'),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->placeholder('-'),
                TextColumn::make('productType.name')
                    ->label('Product'),
                TextColumn::make('grammage')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state.'g'),
                TextColumn::make('quantity')
                    ->label('Qty'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in_stock' => 'success',
                        'dispatched' => 'warning',
                        'destroyed' => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('destinationWarehouse.name')
                    ->label('Destination')
                    ->placeholder('-'),
                TextColumn::make('dispatcher.name')
                    ->label('Dispatched By')
                    ->placeholder('-'),
                TextColumn::make('received_at')
                    ->label('Received At')
                    ->dateTime()
                    ->placeholder('-'),
                TextColumn::make('destroyed_at')
                    ->label('Destroyed At')
                    ->dateTime()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'in_stock' => 'In Stock',
                        'dispatched' => 'Dispatched',
                        'destroyed' => 'Destroyed',
                    ]),
            ])
            ->recordActions([
                Action::make('sendToWarehouse')
                    ->label('Send to Another Warehouse')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->visible(fn (DamagedInventory $record): bool => $record->status === 'in_stock')
                    ->form([
                        Select::make('destination_warehouse_id')
                            ->label('Destination Warehouse')
                            ->options(fn (DamagedInventory $record) => $excludeCurrentWarehouseOptions($record->warehouse_id))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (DamagedInventory $record, array $data) {
                        $record->update([
                            'status' => 'dispatched',
                            'destination_warehouse_id' => $data['destination_warehouse_id'],
                            'dispatched_by' => auth()->id(),
                            'dispatched_at' => now(),
                        ]);

                        $this->dispatch('refresh-dashboard');

                        Notification::make()->title('Damaged stock dispatched — awaiting destination receipt')->success()->send();
                    }),
                Action::make('destroy')
                    ->label('Destroy')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (DamagedInventory $record): bool => $record->status === 'in_stock')
                    ->requiresConfirmation()
                    ->modalHeading('Destroy Damaged Stock')
                    ->modalDescription('This permanently removes the damaged stock from inventory. A reason is required.')
                    ->form([
                        Textarea::make('destroy_reason')
                            ->label('Reason for Destruction')
                            ->required(),
                    ])
                    ->action(function (DamagedInventory $record, array $data) {
                        $record->update([
                            'status' => 'destroyed',
                            'destroyed_by' => auth()->id(),
                            'destroyed_at' => now(),
                            'destroy_reason' => $data['destroy_reason'],
                        ]);

                        $this->dispatch('refresh-dashboard');

                        Notification::make()->title('Damaged stock destroyed')->success()->send();
                    }),
                Action::make('markAsReceived')
                    ->label('Mark as Received')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (DamagedInventory $record): bool {
                        $warehouseIds = auth()->user()->managedWarehouses()->pluck('id')->map(fn ($id): int => (int) $id);

                        return $record->status === 'dispatched'
                            && $warehouseIds->contains((int) $record->destination_warehouse_id);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Damaged Stock Received')
                    ->modalDescription('This confirms the damaged stock has arrived at your warehouse.')
                    ->action(function (DamagedInventory $record) {
                        $record->update([
                            'status' => 'in_stock',
                            'warehouse_id' => $record->destination_warehouse_id,
                            'destination_warehouse_id' => null,
                            'received_by' => auth()->id(),
                            'received_at' => now(),
                        ]);

                        $this->dispatch('refresh-dashboard');

                        Notification::make()->title('Damaged stock received at warehouse')->success()->send();
                    }),
            ])
            ->paginated(20);
    }
}
