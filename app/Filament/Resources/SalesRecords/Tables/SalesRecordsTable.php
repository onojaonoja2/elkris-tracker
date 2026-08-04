<?php

namespace App\Filament\Resources\SalesRecords\Tables;

use App\Filament\Resources\SalesRecords\SalesRecordResource;
use App\Models\SalesRecord;
use App\Services\SalesRecordService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

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
                TextColumn::make('customer.customer_name')
                    ->label('Linked Customer')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),
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
                        'partially_collected' => 'info',
                        'collected' => 'success',
                        'overdue' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('-'),
                TextColumn::make('payment_proof_status')
                    ->label('Payment Proof')
                    ->badge()
                    ->state(fn (SalesRecord $record): bool => $record->hasPaymentProof())
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Uploaded' : 'Missing')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->visible(fn (): bool => auth()->user()->hasAnyRole(['accountant', 'general_accountant', 'supervisor', 'admin'])),
                TextColumn::make('proof_review_status')
                    ->label('Proof Review')
                    ->badge()
                    ->state(fn (SalesRecord $record): string => $record->hasPendingProofReview() ? 'Pending' : 'Not Requested')
                    ->color(fn (SalesRecord $record): string => $record->hasPendingProofReview() ? 'danger' : 'gray')
                    ->visible(fn (): bool => auth()->user()->hasAnyRole(['accountant', 'general_accountant', 'supervisor', 'admin'])),
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
                                'partially_collected' => 'Partially Collected',
                                'collected' => 'Collected',
                                'overdue' => 'Overdue',
                            ])
                            ->placeholder('All'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                ($data['credit_status_filter'] ?? null) === 'overdue',
                                fn (Builder $query) => $query->whereIn('credit_status', ['pending_payment', 'partially_collected'])
                                    ->where('expected_collection_date', '<', now()->toDateString()),
                            )
                            ->when(
                                ($data['credit_status_filter'] ?? null) !== null
                                    && ($data['credit_status_filter'] ?? null) !== ''
                                    && ($data['credit_status_filter'] ?? null) !== 'overdue',
                                fn (Builder $query) => $query->where('credit_status', $data['credit_status_filter']),
                            );
                    }),
            ])
            ->recordActions([

                SalesRecordResource::getViewActionForResource(SalesRecordResource::class),

                // ACCOUNTANT: Approve (verify + confirm stock already deducted)
                Action::make('approveByAccountant')
                    ->label('Approve (Accountant)')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SalesRecord $record) => $record->status === 'pending' && auth()->user()->hasRole('accountant'))
                    ->form(function () {
                        return [
                            Textarea::make('accountant_notes')
                                ->label('Approval Notes'),
                        ];
                    })
                    ->action(function (SalesRecord $record, array $data) {
                        try {
                            SalesRecordService::approve($record, $data, auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Approval failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()->title('Sales record approved')->success()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Sales Record')
                    ->modalDescription('Confirm approval. Stock was already deducted when the sale was submitted.'),

                // ACCOUNTANT: Reject with reason
                Action::make('rejectByAccountant')
                    ->label('Reject (Accountant)')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SalesRecord $record) => $record->status === 'pending' && auth()->user()->hasRole('accountant'))
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->action(function (SalesRecord $record, array $data) {
                        try {
                            SalesRecordService::reject($record, $data['rejection_reason'], auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Rejection failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()->title('Sales record rejected and stock restored')->danger()->send();
                    })
                    ->requiresConfirmation(),

                // Upload Payment Proof (credit sales only)
                Action::make('uploadPaymentProof')
                    ->label('Upload Payment Proof')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('warning')
                    ->visible(fn (SalesRecord $record): bool => $record->is_credit
                        && $record->status === 'approved'
                        && $record->isOutstanding()
                        && self::canAttachPaymentProof($record))
                    ->form([
                        FileUpload::make('payment_proof_path')
                            ->label('Payment Proof')
                            ->image()
                            ->maxSize(2048)
                            ->disk('s3')
                            ->directory('receipts/payment-proofs')
                            ->visibility('private')
                            ->imageEditor()
                            ->required(),
                    ])
                    ->action(function (SalesRecord $record, array $data) {
                        try {
                            SalesRecordService::attachPaymentProof($record, $data, auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Upload failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()->title('Payment proof uploaded')->success()->send();
                    })
                    ->modalHeading('Upload Payment Proof')
                    ->modalDescription('Attach proof of payment for this outstanding credit sale.'),

                // View Payment Proof
                Action::make('viewPaymentProof')
                    ->label('View Payment Proof')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->visible(fn (SalesRecord $record): bool => (bool) $record->payment_proof_path
                        && auth()->user()->hasAnyRole(['accountant', 'general_accountant', 'supervisor', 'admin', 'sales']))
                    ->modalContent(fn (SalesRecord $record) => view('filament.payment-proof', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                // AGENT: Request payment proof review from accountants
                Action::make('requestProofReview')
                    ->label('Request Proof Review')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('danger')
                    ->visible(fn (SalesRecord $record): bool => $record->is_credit
                        && $record->status === 'approved'
                        && $record->isOutstanding()
                        && ! $record->hasPaymentProof()
                        && ! $record->hasPendingProofReview()
                        && $record->agent_id === auth()->id())
                    ->action(function (SalesRecord $record) {
                        try {
                            SalesRecordService::requestProofReview($record, auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Request failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()->title('Proof review requested')->success()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Request Payment Proof Review')
                    ->modalDescription('Notify accountants that you need a payment proof review for this outstanding credit sale.'),

                // ACCOUNTANT: Mark Credit Sale as Collected
                Action::make('markCollected')
                    ->label('Mark as Collected')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (SalesRecord $record) => $record->is_credit
                        && $record->status === 'approved'
                        && $record->isOutstanding()
                        && $record->hasPaymentProof()
                        && auth()->user()->hasRole('accountant'))
                    ->form([
                        Textarea::make('credit_notes')
                            ->label('Collection Notes'),
                    ])
                    ->action(function (SalesRecord $record, array $data) {
                        try {
                            SalesRecordService::markCollected($record, $data, auth()->id());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Collection failed')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

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
                    ->visible(fn (SalesRecord $record) => $record->receipt_path && auth()->user()->hasAnyRole(['accountant', 'supervisor', 'admin']))
                    ->modalContent(fn (SalesRecord $record) => view('filament.sales-record-receipt', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }

    protected static function canAttachPaymentProof(SalesRecord $record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'supervisor', 'accountant', 'general_accountant', 'sales'])) {
            return true;
        }

        return $record->agent_id === $user->id;
    }
}
