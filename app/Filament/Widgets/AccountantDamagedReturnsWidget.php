<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\DamagedStockReturn;
use App\Models\Inventory;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class AccountantDamagedReturnsWidget extends TableWidget
{
    protected static ?string $heading = 'Pending Damaged Stock Returns';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['accountant', 'general_accountant']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => DamagedStockReturn::where('status', 'pending')->orderBy('created_at', 'desc'))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Returned By'),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse'),
                TextColumn::make('productType.name')
                    ->label('Product'),
                TextColumn::make('grammage')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state.'g'),
                TextColumn::make('quantity')
                    ->label('Qty'),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(40),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('approveDamagedReturn')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Damaged Stock Return')
                    ->modalDescription('This will add the damaged stock to the warehouse inventory.')
                    ->action(function (DamagedStockReturn $record) {
                        DB::transaction(function () use ($record) {
                            $inv = Inventory::firstOrCreate(
                                [
                                    'warehouse_id' => $record->warehouse_id,
                                    'product_type_id' => $record->product_type_id,
                                    'grammage' => $record->grammage,
                                ],
                                ['quantity' => 0]
                            );
                            $inv->increment('quantity', $record->quantity);

                            $agentStock = AgentStock::where('user_id', $record->user_id)
                                ->where('product_type_id', $record->product_type_id)
                                ->where('grammage', $record->grammage)
                                ->first();

                            if ($agentStock) {
                                $agentStock->decrement('quantity', $record->quantity);
                            }

                            $record->update([
                                'status' => 'approved',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                            ]);
                        });

                        Notification::make()->title('Damaged stock return approved and inventory updated')->success()->send();
                    }),

                Action::make('rejectDamagedReturn')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Reject Damaged Stock Return')
                    ->action(function (DamagedStockReturn $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        Notification::make()->title('Damaged stock return rejected')->danger()->send();
                    }),
            ])
            ->paginated(10);
    }
}
