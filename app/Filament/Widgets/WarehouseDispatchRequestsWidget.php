<?php

namespace App\Filament\Widgets;

use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class WarehouseDispatchRequestsWidget extends BaseWidget
{
    protected static ?string $heading = 'Pending Warehouse Dispatch Requests';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('warehouse_manager') ?? false;
    }

    public function table(Table $table): Table
    {
        $warehouseIds = auth()->user()->managedWarehouses()->pluck('id');

        return $table
            ->query(fn (): Builder => StockTransfer::query()
                ->where('status', StockTransferStatus::Requested)
                ->whereIn('from_warehouse_id', $warehouseIds)
                ->where(fn (Builder $q) => $q->whereNull('requires_approval')->orWhere('requires_approval', false))
                ->with(['fromWarehouse', 'toAgent', 'items.productType'])
                ->orderBy('created_at', 'desc'))
            ->columns([
                TextColumn::make('id')
                    ->label('Dispatch #')
                    ->sortable(),
                TextColumn::make('fromWarehouse.name')
                    ->label('Warehouse')
                    ->sortable(),
                TextColumn::make('source_name')
                    ->label('Source')
                    ->placeholder('-'),
                TextColumn::make('toAgent.name')
                    ->label('For Agent')
                    ->placeholder('-'),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),
                TextColumn::make('items')
                    ->label('Products')
                    ->state(function (StockTransfer $record): string {
                        return $record->items->map(fn ($item) => ($item->productType?->name ?? 'Unknown').' '.$item->grammage.'g x'.$item->quantity)
                            ->implode(', ');
                    })
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('dispatch')
                    ->label('Dispatch')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (StockTransfer $record) {
                        try {
                            StockTransferService::dispatchByWarehouse($record, auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Dispatch failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Stock dispatched')
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
