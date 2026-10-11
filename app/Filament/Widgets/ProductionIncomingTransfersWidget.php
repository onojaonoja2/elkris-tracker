<?php

namespace App\Filament\Widgets;

use App\Models\RawMaterialTransfer;
use App\Models\Warehouse;
use App\Services\RawMaterialTransferService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class ProductionIncomingTransfersWidget extends BaseWidget
{
    protected static ?string $heading = 'Incoming Raw Material Transfers';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'production_management', 'manager', 'general_manager', 'accountant', 'general_accountant']) ?? false;
    }

    private static function canManage(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'production_management']) ?? false;
    }

    public function table(Table $table): Table
    {
        $warehouseIds = Warehouse::productionModuleIds();

        return $table
            ->query(fn () => RawMaterialTransfer::query()
                ->whereIn('to_warehouse_id', $warehouseIds)
                ->whereIn('status', ['requested', 'dispatched'])
                ->with(['fromWarehouse', 'toWarehouse', 'rawMaterial', 'requester'])
                ->orderBy('created_at', 'desc'))
            ->columns([
                TextColumn::make('id')
                    ->label('Transfer #')
                    ->sortable(),
                TextColumn::make('rawMaterial.name')
                    ->label('Material'),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->formatStateUsing(fn ($state, RawMaterialTransfer $record): string => "{$state} {$record->rawMaterial?->unit_of_measure}"),
                TextColumn::make('fromWarehouse.name')
                    ->label('From Warehouse'),
                TextColumn::make('toWarehouse.name')
                    ->label('To Warehouse'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'received' => 'success',
                        'dispatched' => 'warning',
                        'cancelled' => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('requester.name')
                    ->label('Requested By')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('receive')
                    ->label('Receive')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->size('sm')
                    ->visible(fn (RawMaterialTransfer $record): bool => $record->status === 'dispatched' && self::canManage())
                    ->requiresConfirmation()
                    ->modalHeading('Receive Raw Materials')
                    ->modalDescription(fn (RawMaterialTransfer $record): string => "Confirm receipt of {$record->quantity} {$record->rawMaterial?->unit_of_measure} of {$record->rawMaterial?->name} into {$record->toWarehouse?->name}.")
                    ->modalButton('Confirm Receipt')
                    ->action(function (RawMaterialTransfer $record) {
                        try {
                            RawMaterialTransferService::receiveTransfer($record, auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Receipt failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Raw materials received')
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->size('sm')
                    ->visible(fn (RawMaterialTransfer $record): bool => $record->status === 'requested' && self::canManage())
                    ->requiresConfirmation()
                    ->action(function (RawMaterialTransfer $record) {
                        try {
                            RawMaterialTransferService::cancelTransfer($record);
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Cancellation failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Transfer cancelled')
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),
            ])
            ->paginated([10, 25]);
    }
}
