<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\SalesRecord;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class AccountantSalesRecordsWidget extends TableWidget
{
    protected static ?string $heading = 'Pending Sales Record Verifications';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->role === 'accountant';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SalesRecord::where('status', 'receipt_uploaded')
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
            )
            ->columns([
                TextColumn::make('agent.name')
                    ->label('Agent'),
                TextColumn::make('agent_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open_market' => 'Open Market',
                        'retail_market' => 'Retail Market',
                        default => $state,
                    }),
                TextColumn::make('total_value')
                    ->label('Total (₦)')
                    ->money('NGN'),
                TextColumn::make('vendor_name')
                    ->label('Vendor')
                    ->placeholder('-'),
                TextColumn::make('business_name')
                    ->label('Business')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('approveByAccountant')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form(function () {
                        return [
                            Textarea::make('accountant_notes')
                                ->label('Approval Notes'),
                        ];
                    })
                    ->action(function (SalesRecord $record, array $data) {
                        $products = $record->products ?? [];

                        DB::transaction(function () use ($record, $products, $data) {
                            foreach ($products as $product) {
                                $productName = $product['product_name'] ?? null;
                                $grammage = $product['grammage'] ?? null;
                                $quantity = $product['quantity'] ?? 0;

                                if (! $productName || ! $grammage || $quantity <= 0) {
                                    continue;
                                }

                                if ($record->stockist_id) {
                                    $stock = StockistStock::where('stockist_id', $record->stockist_id)
                                        ->where('product_name', $productName)
                                        ->where('grammage', $grammage)
                                        ->lockForUpdate()
                                        ->first();

                                    if (! $stock || $stock->quantity < $quantity) {
                                        Notification::make()
                                            ->danger()
                                            ->title('Insufficient stock')
                                            ->body("Stockist doesn't have enough {$productName} ({$grammage}g). Available: ".($stock->quantity ?? 0))
                                            ->send();

                                        return;
                                    }

                                    $stock->decrement('quantity', $quantity);
                                }

                                if ($record->agent_id) {
                                    $agentStock = AgentStock::where('user_id', $record->agent_id)
                                        ->where('product_name', $productName)
                                        ->where('grammage', $grammage)
                                        ->lockForUpdate()
                                        ->first();

                                    if (! $agentStock || $agentStock->quantity < $quantity) {
                                        Notification::make()
                                            ->danger()
                                            ->title('Insufficient agent stock')
                                            ->body("Agent doesn't have enough {$productName} ({$grammage}g). Available: ".($agentStock->quantity ?? 0))
                                            ->send();

                                        return;
                                    }

                                    $agentStock->decrement('quantity', $quantity);
                                }
                            }

                            if ($record->stockist_id) {
                                StockistTransaction::create([
                                    'stockist_id' => $record->stockist_id,
                                    'user_id' => auth()->id(),
                                    'field_agent_id' => $record->agent_id,
                                    'sales_record_id' => $record->id,
                                    'type' => 'deducted',
                                    'amount' => $record->total_value,
                                    'description' => "Auto-deducted for sales record #{$record->id}",
                                    'transaction_date' => now()->toDateString(),
                                ]);

                                $record->stockist?->decrement('stock_balance', $record->total_value);
                            }

                            if ($record->agent_id) {
                                $record->agent?->increment('stock_balance', $record->total_value);
                            }

                            $record->update([
                                'status' => 'approved',
                                'accountant_verified_at' => now(),
                                'accountant_verified_by' => auth()->id(),
                                'accountant_notes' => $data['accountant_notes'] ?? null,
                            ]);
                        });

                        Notification::make()->title('Sales record approved and stock deducted')->success()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Sales Record')
                    ->modalDescription('Confirm approval. Stock will be deducted from the creator\'s stock.'),

                Action::make('rejectByAccountant')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('accountant_notes')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->action(function (SalesRecord $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'accountant_verified_at' => now(),
                            'accountant_verified_by' => auth()->id(),
                            'accountant_notes' => $data['accountant_notes'] ?? null,
                        ]);
                        Notification::make()->title('Sales record rejected')->danger()->send();
                    })
                    ->requiresConfirmation(),

                Action::make('viewReceipt')
                    ->label('View Receipt')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->visible(fn (SalesRecord $record) => $record->receipt_path)
                    ->modalContent(fn (SalesRecord $record) => view('filament.sales-record-receipt', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->paginated(false);
    }
}
