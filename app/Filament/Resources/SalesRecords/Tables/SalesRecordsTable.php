<?php

namespace App\Filament\Resources\SalesRecords\Tables;

use App\Models\AgentStock;
use App\Models\SalesRecord;
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
                TextColumn::make('is_credit')
                    ->label('Sale Type')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Credit' : 'Paid')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->placeholder('-')
                    ->searchable(),
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
                TextColumn::make('expected_collection_date')
                    ->label('Expected Date')
                    ->date()
                    ->placeholder('-'),
                TextColumn::make('credit_status')
                    ->label('Credit Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str_replace('_', ' ', ucfirst($state ?? '')))
                    ->color(fn (?string $state): string => match ($state) {
                        'pending_payment' => 'warning',
                        'collected' => 'success',
                        'overdue' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('-'),
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

                Filter::make('is_credit')
                    ->label('Sale Type')
                    ->form([
                        Select::make('sale_type')
                            ->label('Type')
                            ->options([
                                'paid' => 'Paid',
                                'credit' => 'Credit',
                            ])
                            ->placeholder('All'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                ($data['sale_type'] ?? null) === 'paid',
                                fn (Builder $query) => $query->where('is_credit', false),
                            )
                            ->when(
                                ($data['sale_type'] ?? null) === 'credit',
                                fn (Builder $query) => $query->where('is_credit', true),
                            );
                    }),

                Filter::make('credit_status')
                    ->label('Credit Status')
                    ->form([
                        Select::make('credit_status_filter')
                            ->label('Status')
                            ->options([
                                'pending_payment' => 'Pending Payment',
                                'collected' => 'Collected',
                                'overdue' => 'Overdue',
                            ])
                            ->placeholder('All'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                ($data['credit_status_filter'] ?? null) !== null && ($data['credit_status_filter'] ?? null) !== '',
                                fn (Builder $query) => $query->where('credit_status', $data['credit_status_filter']),
                            );
                    }),
            ])
            ->recordActions([

                // ACCOUNTANT: Approve (verify + auto-deduct from creator's stock)
                Action::make('approveByAccountant')
                    ->label('Approve (Accountant)')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SalesRecord $record) => $record->status === 'pending' && auth()->user()->role === 'accountant')
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

                            if (! $record->is_credit && $record->agent_id) {
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

                // ACCOUNTANT: Reject with reason
                Action::make('rejectByAccountant')
                    ->label('Reject (Accountant)')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SalesRecord $record) => $record->status === 'pending' && auth()->user()->role === 'accountant')
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

                // ACCOUNTANT: Mark Credit Sale as Collected
                Action::make('markCollected')
                    ->label('Mark as Collected')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (SalesRecord $record) => $record->is_credit
                        && $record->status === 'approved'
                        && $record->credit_status === 'pending_payment'
                        && auth()->user()->role === 'accountant')
                    ->form([
                        Textarea::make('credit_notes')
                            ->label('Collection Notes'),
                    ])
                    ->action(function (SalesRecord $record, array $data) {
                        if ($record->agent_id) {
                            $record->agent?->increment('stock_balance', $record->total_value);
                        }

                        $record->update([
                            'credit_status' => 'collected',
                            'collected_at' => now(),
                            'collected_by' => auth()->id(),
                            'credit_notes' => $data['credit_notes'] ?? null,
                        ]);

                        Notification::make()->title('Credit sale marked as collected')->success()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Mark Credit as Collected')
                    ->modalDescription('Confirm payment has been received. The agent\'s stock balance will be credited.'),

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
