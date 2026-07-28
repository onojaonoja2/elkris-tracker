<?php

namespace App\Filament\Widgets;

use App\Models\DamagedStockReturn;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class WarehouseDamagedReturnsWidget extends TableWidget
{
    protected static ?string $heading = 'Damaged Stock Returns';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasRole('warehouse_manager');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $user = auth()->user();
                $warehouseIds = Warehouse::where('manager_id', $user->id)->pluck('id');

                return DamagedStockReturn::whereIn('warehouse_id', $warehouseIds)
                    ->where('status', 'approved')
                    ->orderBy('created_at', 'desc');
            })
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('user.name')->label('Returned By'),
                TextColumn::make('warehouse.name')->label('Warehouse'),
                TextColumn::make('productType.name')->label('Product'),
                TextColumn::make('grammage')->label('Weight')->formatStateUsing(fn ($state) => $state.'g'),
                TextColumn::make('quantity')->label('Qty'),
                TextColumn::make('status')->badge(),
                TextColumn::make('return_stage')
                    ->label('Stage')
                    ->badge()
                    ->state(fn (DamagedStockReturn $record): string => match (true) {
                        is_null($record->return_to_warehouse_initiated_at) => 'Pending Initiation',
                        is_null($record->return_received_at) => 'Awaiting Receipt',
                        default => 'Completed',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Pending Initiation' => 'warning',
                        'Awaiting Receipt' => 'info',
                        'Completed' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('return_stage')
                    ->label('Stage')
                    ->options([
                        'pending_initiation' => 'Pending Initiation',
                        'awaiting_receipt' => 'Awaiting Receipt',
                        'completed' => 'Completed',
                    ])
                    ->query(fn ($query, $data) => match ($data['value'] ?? null) {
                        'pending_initiation' => $query->whereNull('return_to_warehouse_initiated_at'),
                        'awaiting_receipt' => $query->whereNotNull('return_to_warehouse_initiated_at')->whereNull('return_received_at'),
                        'completed' => $query->whereNotNull('return_received_at'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('initiateReturn')
                    ->label('Initiate Return to Warehouse')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('warning')
                    ->visible(fn (DamagedStockReturn $record): bool => is_null($record->return_to_warehouse_initiated_at))
                    ->requiresConfirmation()
                    ->modalHeading('Initiate Damaged Stock Return')
                    ->modalDescription('Mark this damaged stock return as being processed for warehouse return.')
                    ->action(function (DamagedStockReturn $record) {
                        $record->update([
                            'return_to_warehouse_initiated_by' => auth()->id(),
                            'return_to_warehouse_initiated_at' => now(),
                        ]);

                        Notification::make()->title('Return initiated — awaiting receipt confirmation')->success()->send();
                    }),

                Action::make('receiveReturn')
                    ->label('Mark as Received')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (DamagedStockReturn $record): bool => ! is_null($record->return_to_warehouse_initiated_at) && is_null($record->return_received_at))
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Damaged Stock Return Received')
                    ->modalDescription('This confirms that the damaged stock has been received at the warehouse.')
                    ->form([
                        Textarea::make('notes')->label('Receipt Notes'),
                    ])
                    ->action(function (DamagedStockReturn $record) {
                        $record->update([
                            'return_received_by' => auth()->id(),
                            'return_received_at' => now(),
                            'status' => 'returned',
                        ]);

                        Notification::make()->title('Damaged stock return completed')->success()->send();
                    }),
            ])
            ->paginated(10);
    }
}
