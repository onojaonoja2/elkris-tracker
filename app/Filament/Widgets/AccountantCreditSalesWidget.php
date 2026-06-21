<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class AccountantCreditSalesWidget extends TableWidget
{
    protected static ?string $heading = 'Credit Sales';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['accountant', 'general_accountant']);
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
                Action::make('markCollected')
                    ->label('Mark as Collected')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (SalesRecord $record) => $record->credit_status === 'pending_payment')
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
            ]);
    }
}
