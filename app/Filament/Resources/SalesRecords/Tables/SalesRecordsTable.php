<?php

namespace App\Filament\Resources\SalesRecords\Tables;

use App\Models\AgentStock;
use App\Models\SalesRecord;
use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SalesRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('agent.name')
                    ->label('Agent')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('agent_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open_market' => 'Open Market',
                        'retail_market' => 'Retail Market',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'open_market' => 'warning',
                        'retail_market' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('vendor_name')
                    ->label('Market / Vendor')
                    ->placeholder('-'),
                TextColumn::make('business_name')
                    ->label('Business')
                    ->placeholder('-'),
                TextColumn::make('total_value')
                    ->label('Total (₦)')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('products')
                    ->label('Products')
                    ->formatStateUsing(fn ($products) => collect($products)->map(fn ($p) => "{$p['quantity']}x {$p['product_name']}")->implode(', '))
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'receipt_uploaded' => 'info',
                        'verified_by_accountant' => 'primary',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),
                TextColumn::make('stockist.name')
                    ->label('Stockist')
                    ->placeholder('Unassigned'),
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
            ->recordActions([

                // ACCOUNTANT: Approve (verify + select stockist + deduct stock)
                Action::make('approveByAccountant')
                    ->label('Approve (Accountant)')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SalesRecord $record) => $record->status === 'receipt_uploaded' && auth()->user()->role === 'accountant')
                    ->form(function (SalesRecord $record) {
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
                    ->action(function (SalesRecord $record, array $data) {
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
                                            'type' => 'deducted',
                                            'amount' => $lineTotal,
                                            'description' => "Deducted {$quantity}x {$productName} ({$grammage}g) for sales record #{$record->id}",
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
                                'stockist_balance' => $record->total_value,
                                'accountant_verified_at' => now(),
                                'accountant_verified_by' => auth()->id(),
                                'accountant_notes' => $data['accountant_notes'] ?? null,
                            ]);
                        });

                        Notification::make()->title('Sales record approved and stock deducted')->success()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Sales Record')
                    ->modalDescription('Select the stockist to deduct stock from. Stock availability will be verified.'),

                // ACCOUNTANT: Reject with reason
                Action::make('rejectByAccountant')
                    ->label('Reject (Accountant)')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SalesRecord $record) => $record->status === 'receipt_uploaded' && auth()->user()->role === 'accountant')
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

                // View Receipt
                Action::make('viewReceipt')
                    ->label('View Receipt')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->visible(fn (SalesRecord $record) => $record->receipt_path && in_array(auth()->user()->role, ['accountant', 'supervisor', 'admin']))
                    ->modalContent(fn (SalesRecord $record) => view('filament.sales-record-receipt', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }
}
