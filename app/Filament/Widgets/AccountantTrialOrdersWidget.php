<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
use App\Models\TrialOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
                TrialOrder::where('status', 'receipt_uploaded')
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
                    ->form(function (TrialOrder $record) {
                        return [
                            Select::make('stockist_id')
                                ->label('Select Stockist for Deduction')
                                ->options(fn () => Stockist::whereIn('id', function ($q) {
                                    $q->select('stockist_id')->from('stockist_stocks')->where('quantity', '>', 0);
                                })->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->live(),
                            Textarea::make('accountant_notes')
                                ->label('Approval Notes'),
                        ];
                    })
                    ->action(function (TrialOrder $record, array $data) {
                        $stockistId = $data['stockist_id'];
                        $stockist = Stockist::find($stockistId);
                        $products = $record->products ?? [];

                        foreach ($products as $product) {
                            $productName = $product['product_name'] ?? null;
                            $grammage = $product['grammage'] ?? null;
                            $quantity = $product['quantity'] ?? 0;

                            if ($productName && $grammage && $quantity > 0) {
                                $stock = StockistStock::where('stockist_id', $stockistId)
                                    ->where('product_name', $productName)
                                    ->where('grammage', $grammage)
                                    ->first();

                                if (! $stock || $stock->quantity < $quantity) {
                                    Notification::make()
                                        ->danger()
                                        ->title('Insufficient stock')
                                        ->body("{$stockist->name} does not have enough {$productName} ({$grammage}g). Available: ".($stock->quantity ?? 0).", Required: {$quantity}")
                                        ->send();

                                    return;
                                }

                                if ($record->agent_id) {
                                    $agentStock = AgentStock::where('user_id', $record->agent_id)
                                        ->where('product_name', $productName)
                                        ->where('grammage', $grammage)
                                        ->first();

                                    if (! $agentStock || $agentStock->quantity < $quantity) {
                                        Notification::make()
                                            ->danger()
                                            ->title('Insufficient agent stock')
                                            ->body("Agent does not have enough {$productName} ({$grammage}g). Available: ".($agentStock->quantity ?? 0).", Required: {$quantity}")
                                            ->send();

                                        return;
                                    }
                                }
                            }
                        }

                        DB::transaction(function () use ($record, $products, $stockistId, $stockist, $data) {
                            foreach ($products as $product) {
                                $productName = $product['product_name'] ?? null;
                                $grammage = $product['grammage'] ?? null;
                                $quantity = $product['quantity'] ?? 0;
                                $price = $product['price'] ?? 0;
                                $lineTotal = $quantity * $price;

                                if ($productName && $grammage && $quantity > 0) {
                                    $stockistStock = StockistStock::where('stockist_id', $stockistId)
                                        ->where('product_name', $productName)
                                        ->where('grammage', $grammage)
                                        ->lockForUpdate()
                                        ->first();

                                    if ($stockistStock && $stockistStock->quantity >= $quantity) {
                                        $stockistStock->decrement('quantity', $quantity);

                                        if ($record->agent_id) {
                                            AgentStock::where('user_id', $record->agent_id)
                                                ->where('product_name', $productName)
                                                ->where('grammage', $grammage)
                                                ->decrement('quantity', $quantity);
                                        }

                                        StockistTransaction::create([
                                            'stockist_id' => $stockistId,
                                            'user_id' => auth()->id(),
                                            'field_agent_id' => $record->agent_id,
                                            'trial_order_id' => $record->id,
                                            'type' => 'deducted',
                                            'amount' => $lineTotal,
                                            'description' => "Deducted {$quantity}x {$productName} ({$grammage}g) for trial order #{$record->id}",
                                            'transaction_date' => now()->toDateString(),
                                        ]);
                                    }
                                }
                            }

                            $stockist->decrement('stock_balance', $record->total_value);

                            if ($record->agent_id) {
                                $record->agent?->increment('stock_balance', $record->total_value);
                            }

                            $record->update([
                                'status' => 'approved',
                                'stockist_id' => $stockistId,
                                'accountant_verified_at' => now(),
                                'accountant_verified_by' => auth()->id(),
                                'accountant_notes' => $data['accountant_notes'] ?? null,
                                'payment_status' => TrialOrder::PAYMENT_STATUS_COMPLETED,
                            ]);
                        });

                        Notification::make()->title('Trial order approved and stock deducted')->success()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Trial Order')
                    ->modalDescription('Select the stockist to deduct stock from. Stock availability will be verified.'),

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
                            'status' => 'rejected',
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
