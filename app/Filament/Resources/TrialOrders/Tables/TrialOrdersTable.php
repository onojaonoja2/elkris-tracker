<?php

namespace App\Filament\Resources\TrialOrders\Tables;

use App\Enums\PaymentStatus;
use App\Enums\TrialOrderStatus;
use App\Filament\Exports\TrialOrderExporter;
use App\Models\TrialOrder;
use App\Services\TrialOrderService;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
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
                    ->label('Agent')
                    ->searchable()
                    ->sortable()
                    ->visible(fn ($record) => $record && $record->agent_id !== null),
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

                // Attribute Sale (supervisor only)
                Action::make('attributeSale')
                    ->label('Attribute Sale')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('info')
                    ->visible(fn ($record) => $record->payment_status === PaymentStatus::Pending && auth()->user()->role === 'supervisor')
                    ->requiresConfirmation()
                    ->modalHeading('Attribute Sale')
                    ->modalDescription('This will attribute the sale, deduct stock, and mark payment as completed.')
                    ->action(function (TrialOrder $record) {
                        $service = app(TrialOrderService::class);
                        $service->attributeSale($record);
                    }),
            ]);
    }
}
