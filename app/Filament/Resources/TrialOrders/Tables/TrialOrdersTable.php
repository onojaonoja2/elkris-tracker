<?php

namespace App\Filament\Resources\TrialOrders\Tables;

use App\Enums\PaymentStatus;
use App\Enums\TrialOrderStatus;
use App\Filament\Exports\TrialOrderExporter;
use App\Models\Stockist;
use App\Models\TrialOrder;
use App\Services\TrialOrderService;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->color(fn (TrialOrderStatus $state, TrialOrder $record): string => match ($state) {
                        TrialOrderStatus::Pending => 'warning',
                        TrialOrderStatus::ReceiptUploaded => 'info',
                        TrialOrderStatus::VerifiedByAccountant => 'primary',
                        TrialOrderStatus::Approved => $record->isLocked() ? 'success' : 'info',
                        TrialOrderStatus::Rejected => 'danger',
                    })
                    ->label('Status'),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (PaymentStatus $state): string => $state->color())
                    ->label('Payment Status'),
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

                // ACCOUNTANT: Approve (verify + auto-deduct from creator's stock)
                Action::make('approveByAccountant')
                    ->label('Approve (Accountant)')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (TrialOrder $record) => $record->status === TrialOrderStatus::ReceiptUploaded && auth()->user()->role === 'accountant')
                    ->form(function () {
                        return [
                            Textarea::make('accountant_notes')
                                ->label('Approval Notes'),
                        ];
                    })
                    ->action(function (TrialOrder $record, array $data) {
                        $service = app(TrialOrderService::class);
                        $service->approveByAccountant($record, $data['accountant_notes'] ?? null);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Trial Order')
                    ->modalDescription('Confirm approval. Stock will be deducted from the creator\'s stock.'),

                // ACCOUNTANT: Reject with reason
                Action::make('rejectByAccountant')
                    ->label('Reject (Accountant)')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (TrialOrder $record) => $record->status === TrialOrderStatus::ReceiptUploaded && auth()->user()->role === 'accountant')
                    ->form([
                        Textarea::make('accountant_notes')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->action(function (TrialOrder $record, array $data) {
                        $service = app(TrialOrderService::class);
                        $service->rejectByAccountant($record, $data['accountant_notes']);
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
                    ->visible(fn ($record) => $record->payment_status === PaymentStatus::Pending && auth()->user()->role === 'admin')
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
                        $service = app(TrialOrderService::class);
                        $service->processPayment($record, $data);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Payment Received')
                    ->modalDescription('This will confirm payment, deduct stock from the appropriate stockist, and lock the trial order.')
                    ->modalButton('Confirm'),
            ]);
    }
}
