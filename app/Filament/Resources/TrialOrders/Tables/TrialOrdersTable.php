<?php

namespace App\Filament\Resources\TrialOrders\Tables;

use App\Filament\Exports\TrialOrderExporter;
use App\Models\AgentStock;
use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
use App\Models\TrialOrder;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TrialOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('agent.name')
                    ->label('Field Agent')
                    ->searchable()
                    ->sortable()
                    ->visible(fn ($record) => $record && $record->agent_id !== null),
                TextColumn::make('stockist.name')
                    ->label('Stockist')
                    ->searchable()
                    ->sortable()
                    ->visible(fn ($record) => $record && $record->stockist_id !== null),
                TextColumn::make('total_value')
                    ->label('Total Value (₦)')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('products')
                    ->label('Products')
                    ->formatStateUsing(fn ($products) => collect($products)->map(fn ($p) => "{$p['quantity']}x {$p['product_name']}")->implode(', '))
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state, TrialOrder $record): string => match ($state) {
                        'pending' => 'warning',
                        'receipt_uploaded' => 'info',
                        'verified_by_accountant' => 'primary',
                        'approved' => $record->isLocked() ? 'success' : 'info',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'completed' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('accountantVerifier.name')
                    ->label('Verified By (Acct)')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('From Date')
                            ->closeOnDateSelection(),
                        DatePicker::make('created_until')
                            ->label('To Date')
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(TrialOrderExporter::class),
            ])
            ->recordActions([

                // ACCOUNTANT: Approve (verify + select stockist + deduct stock)
                Action::make('approveByAccountant')
                    ->label('Approve (Accountant)')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (TrialOrder $record) => $record->status === 'receipt_uploaded' && auth()->user()->role === 'accountant')
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

                // ACCOUNTANT: Reject with reason
                Action::make('rejectByAccountant')
                    ->label('Reject (Accountant)')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (TrialOrder $record) => $record->status === 'receipt_uploaded' && auth()->user()->role === 'accountant')
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

                // View Receipt (all roles)
                Action::make('viewReceipt')
                    ->label('View Receipt')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->visible(fn (TrialOrder $record) => $record->receipt_path && in_array(auth()->user()->role, ['accountant', 'supervisor', 'admin']))
                    ->modalContent(fn (TrialOrder $record) => view('filament.trial-order-receipt', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                // Confirm Payment (admin only, for legacy flows)
                Action::make('confirmPayment')
                    ->label('Confirm Payment')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('info')
                    ->visible(fn ($record) => $record->payment_status === 'pending' && auth()->user()->role === 'admin')
                    ->form([
                        Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                'cash' => 'Cash',
                                'transfer' => 'Bank Transfer',
                                'pos' => 'POS',
                            ])
                            ->required(),
                        Select::make('balance_holder')
                            ->label('Hold Balance With')
                            ->options([
                                'agent' => 'Field Agent',
                                'stockist' => 'Stockist',
                            ])
                            ->default('agent')
                            ->required()
                            ->live(),
                        Select::make('stockist_id')
                            ->label('Select Stockist')
                            ->options(function () {
                                return Stockist::where('supervisor_id', auth()->id())
                                    ->get()
                                    ->mapWithKeys(fn ($stockist) => [
                                        $stockist->id => $stockist->name.' ('.$stockist->city.')',
                                    ])
                                    ->toArray();
                            })
                            ->visible(fn ($get) => $get('balance_holder') === 'stockist')
                            ->required(fn ($get) => $get('balance_holder') === 'stockist')
                            ->live(),
                    ])
                    ->action(function ($record, array $data) {
                        self::processPayment($record, $data);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Payment Received')
                    ->modalDescription('This will confirm payment, deduct stock from the appropriate stockist, and lock the trial order.')
                    ->modalButton('Confirm'),
            ]);
    }

    private static function attributeSale(TrialOrder $record): void
    {
        $products = $record->products ?? [];

        DB::transaction(function () use ($record, $products) {
            // Deduct from agent's stock balance if it's an agent trial order
            if ($record->agent_id) {
                $agent = $record->agent;
                if ($agent) {
                    $agent->increment('stock_balance', $record->total_value);
                }

                // Create stockist transaction for tracking
                StockistTransaction::create([
                    'user_id' => auth()->id(),
                    'field_agent_id' => $record->agent_id,
                    'trial_order_id' => $record->id,
                    'type' => 'deducted',
                    'amount' => $record->total_value,
                    'description' => 'Trial order sale attributed - supervisor approved',
                    'transaction_date' => now()->toDateString(),
                ]);
            }

            // Deduct from stockist's stock if it's a stockist trial order
            if ($record->stockist_id) {
                foreach ($products as $product) {
                    $productName = $product['product_name'] ?? null;
                    $grammage = $product['grammage'] ?? null;
                    $quantity = $product['quantity'] ?? 0;

                    if ($productName && $grammage && $quantity > 0) {
                        $stock = StockistStock::where('stockist_id', $record->stockist_id)
                            ->where('product_name', $productName)
                            ->where('grammage', $grammage)
                            ->first();

                        if ($stock && $stock->quantity >= $quantity) {
                            $stock->decrement('quantity', $quantity);
                        }
                    }
                }

                $record->stockist?->decrement('stock_balance', $record->total_value);
            }

            $record->update([
                'payment_status' => TrialOrder::PAYMENT_STATUS_COMPLETED,
            ]);
        });
    }

    private static function processPayment($record, array $data): void
    {
        $balanceHolder = $data['balance_holder'] ?? 'agent';
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $selectedStockistId = $data['stockist_id'] ?? null;

        $agent = $record->agent;
        $products = $record->products ?? [];

        $stockist = null;

        if ($balanceHolder === 'stockist' && $selectedStockistId) {
            $stockist = Stockist::find($selectedStockistId);
        }

        if (! $stockist && $balanceHolder === 'agent') {
            $stockist = Stockist::where('supervisor_id', auth()->id())
                ->whereIn('city', (array) ($agent?->assigned_cities ?? []))
                ->first();
        }

        if (! $stockist) {
            Notification::make()
                ->danger()
                ->title('No stockist found')
                ->body('No stockist found with available stock. Please select a stockist with sufficient inventory.')
                ->send();

            return;
        }

        foreach ($products as $product) {
            $productName = $product['product_name'] ?? null;
            $grammage = $product['grammage'] ?? null;
            $quantity = $product['quantity'] ?? 0;

            if ($productName && $grammage && $quantity > 0) {
                $stock = StockistStock::where('stockist_id', $stockist->id)
                    ->where('product_name', $productName)
                    ->where('grammage', $grammage)
                    ->first();

                if (! $stock || $stock->quantity < $quantity) {
                    Notification::make()
                        ->danger()
                        ->title('Insufficient stock')
                        ->body("Insufficient stock for {$productName} ({$grammage}g). Available: ".($stock->quantity ?? 0).", Requested: {$quantity}")
                        ->send();

                    return;
                }
            }
        }

        DB::transaction(function () use ($stockist, $products, $record, $balanceHolder, $paymentMethod, $agent) {
            foreach ($products as $product) {
                $productName = $product['product_name'] ?? null;
                $grammage = $product['grammage'] ?? null;
                $quantity = $product['quantity'] ?? 0;

                if ($productName && $grammage && $quantity > 0) {
                    $stockistStock = StockistStock::firstOrCreate(
                        [
                            'stockist_id' => $stockist->id,
                            'product_name' => $productName,
                            'grammage' => $grammage,
                        ],
                        ['quantity' => 0]
                    );

                    $stockistStock = StockistStock::where('id', $stockistStock->id)
                        ->lockForUpdate()
                        ->first();

                    if ($stockistStock->quantity < $quantity) {
                        throw new \Exception("Insufficient stock: {$productName} ({$grammage}g). Available: {$stockistStock->quantity}, Requested: {$quantity}");
                    }

                    $stockistStock->decrement('quantity', $quantity);
                }
            }

            $updateData = [
                'payment_status' => 'completed',
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'stockist_id' => $stockist->id,
            ];

            if ($balanceHolder === 'agent' && $agent) {
                $updateData['agent_balance'] = $record->total_value;
                $updateData['stockist_balance'] = 0;
                $agent->increment('stock_balance', $record->total_value);
            } else {
                $updateData['agent_balance'] = 0;
                $updateData['stockist_balance'] = $record->total_value;
                $stockist->decrement('stock_balance', $record->total_value);
            }

            $record->update($updateData);

            StockistTransaction::create([
                'stockist_id' => $stockist->id,
                'user_id' => auth()->id(),
                'field_agent_id' => $agent?->id,
                'trial_order_id' => $record->id,
                'type' => 'deducted',
                'amount' => $record->total_value,
                'description' => "Trial order approved - Payment via {$paymentMethod}, Balance held with {$balanceHolder}",
                'transaction_date' => now()->toDateString(),
            ]);
        });
    }
}
