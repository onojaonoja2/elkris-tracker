<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\StockCount;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class AccountantStockCountApprovalWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'Stock Count Approvals';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['accountant', 'general_accountant']);
    }

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn () => StockCount::where('status', 'pending')
                    ->with(['user', 'warehouse', 'items.productType'])
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Submitted By')
                    ->searchable(),

                TextColumn::make('user.role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'community_sales_representative' => 'info',
                        'sales' => 'warning',
                        'warehouse_manager' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'community_sales_representative' => 'CSR',
                        'sales' => 'Sales',
                        'warehouse_manager' => 'Warehouse',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    }),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->placeholder('N/A'),

                TextColumn::make('items')
                    ->label('Items')
                    ->formatStateUsing(fn (StockCount $record): string => $record->items->map(
                        fn ($item) => "{$item->product_name} ({$item->grammage}g): {$item->quantity}"
                    )->implode(', '))
                    ->limit(60),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(30)
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->size('sm')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Stock Count')
                    ->modalDescription('This will replace the current inventory with the submitted physical count. Continue?')
                    ->modalButton('Approve')
                    ->action(function (StockCount $record) {
                        DB::transaction(function () use ($record) {
                            $user = $record->user;
                            $isWarehouse = $record->warehouse_id !== null;

                            foreach ($record->items as $item) {
                                if ($isWarehouse) {
                                    $inventory = Inventory::firstOrCreate(
                                        [
                                            'warehouse_id' => $record->warehouse_id,
                                            'product_type_id' => $item->product_type_id,
                                            'grammage' => $item->grammage,
                                        ],
                                        ['quantity' => 0]
                                    );
                                    $inventory->update(['quantity' => $item->quantity]);
                                } else {
                                    $agentStock = AgentStock::firstOrCreate(
                                        [
                                            'user_id' => $record->user_id,
                                            'product_name' => $item->product_name,
                                            'grammage' => $item->grammage,
                                        ],
                                        [
                                            'product_type_id' => $item->product_type_id,
                                            'quantity' => 0,
                                        ]
                                    );
                                    $agentStock->update(['quantity' => $item->quantity]);
                                }
                            }

                            $record->update([
                                'status' => 'approved',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                            ]);
                        });

                        Notification::make()
                            ->title('Stock count approved')
                            ->body("Inventory updated for {$record->user->name}.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->size('sm')
                    ->form([
                        Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required(),
                    ])
                    ->action(function (StockCount $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                            'rejection_reason' => $data['reason'],
                        ]);

                        Notification::make()
                            ->title('Stock count rejected')
                            ->body("Stock count #{$record->id} has been rejected.")
                            ->danger()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
