<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use App\Services\SalesRecordService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class AccountantCreditSalesWidget extends TableWidget
{
    protected static ?string $heading = 'Credit Sales';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['accountant', 'general_accountant']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SalesRecord::where('is_credit', true)
                    ->where('status', 'approved')
                    ->with('agent')
            )
            ->columns([
                TextColumn::make('agent.name')
                    ->label('Sales Person')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('total_value')
                    ->label('Amount (₦)')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('expected_collection_date')
                    ->label('Expected Date')
                    ->date()
                    ->sortable()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'gray'),
                TextColumn::make('credit_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str_replace('_', ' ', ucfirst($state ?? '')))
                    ->color(fn (?string $state): string => match ($state) {
                        'pending_payment' => 'warning',
                        'collected' => 'success',
                        'overdue' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('payment_proof_status')
                    ->label('Payment Proof')
                    ->badge()
                    ->state(fn (SalesRecord $record): bool => $record->hasPaymentProof())
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Uploaded' : 'Missing')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('collected_at')
                    ->label('Collected On')
                    ->dateTime()
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(),
            ])
            ->defaultSort('expected_collection_date', 'asc')
            ->paginated(15)
            ->recordActions([
                Action::make('uploadPaymentProof')
                    ->label('Upload Payment Proof')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('warning')
                    ->visible(fn (SalesRecord $record): bool => $record->credit_status === 'pending_payment' && ! $record->hasPaymentProof())
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
                    }),

                Action::make('viewPaymentProof')
                    ->label('View Payment Proof')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->visible(fn (SalesRecord $record): bool => (bool) $record->payment_proof_path)
                    ->modalContent(fn (SalesRecord $record) => view('filament.payment-proof', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('markCollected')
                    ->label('Mark as Collected')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (SalesRecord $record): bool => $record->credit_status === 'pending_payment' && $record->hasPaymentProof())
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
            ]);
    }
}
