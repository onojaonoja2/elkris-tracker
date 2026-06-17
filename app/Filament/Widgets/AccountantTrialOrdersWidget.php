<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Enums\TrialOrderStatus;
use App\Models\AgentStock;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
use App\Models\TrialOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class AccountantTrialOrdersWidget extends TableWidget
{
    protected static ?string $heading = 'Pending Trial Order Verifications';

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
                TrialOrder::where('status', TrialOrderStatus::ReceiptUploaded)
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
            )
            ->columns([
                TextColumn::make('agent.name')
                    ->label('Agent')
                    ->placeholder('-'),
                TextColumn::make('stockist.name')
                    ->label('Stockist')
                    ->placeholder('-'),
                TextColumn::make('total_value')
                    ->label('Total (₦)')
                    ->money('NGN'),
                TextColumn::make('products')
                    ->label('Products')
                    ->formatStateUsing(fn ($products) => collect($products)->map(fn ($p) => "{$p['quantity']}x {$p['product_name']}")->implode(', '))
                    ->limit(50),
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
                    ->action(function (TrialOrder $record, array $data) {
                        $products = $record->products ?? [];

                        DB::transaction(function () use ($record, $products, $data) {
                            foreach ($products as $product) {
                                $productName = $product['product_name'] ?? null;
                                $grammage = $product['grammage'] ?? null;
                                $quantity = $product['quantity'] ?? 0;
                                $price = $product['price'] ?? 0;
                                $lineTotal = $quantity * $price;

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
                                    'trial_order_id' => $record->id,
                                    'type' => 'deducted',
                                    'amount' => $record->total_value,
                                    'description' => "Auto-deducted for trial order #{$record->id}",
                                    'transaction_date' => now()->toDateString(),
                                ]);

                                $record->stockist?->decrement('stock_balance', $record->total_value);
                            }

                            if ($record->agent_id) {
                                $record->agent?->increment('stock_balance', $record->total_value);
                            }

                            $record->update([
                                'status' => TrialOrderStatus::Approved,
                                'accountant_verified_at' => now(),
                                'accountant_verified_by' => auth()->id(),
                                'accountant_notes' => $data['accountant_notes'] ?? null,
                                'payment_status' => PaymentStatus::Completed,
                            ]);
                        });

                        Notification::make()->title('Trial order approved and stock deducted')->success()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Trial Order')
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
                    ->action(function (TrialOrder $record, array $data) {
                        $record->update([
                            'status' => TrialOrderStatus::Rejected,
                            'accountant_verified_at' => now(),
                            'accountant_verified_by' => auth()->id(),
                            'accountant_notes' => $data['accountant_notes'] ?? null,
                        ]);
                        Notification::make()->title('Trial order rejected')->danger()->send();
                    })
                    ->requiresConfirmation(),

                Action::make('viewReceipt')
                    ->label('View Receipt')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->visible(fn (TrialOrder $record) => $record->receipt_path)
                    ->modalContent(fn (TrialOrder $record) => view('filament.trial-order-receipt', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->paginated(false);
    }
}
