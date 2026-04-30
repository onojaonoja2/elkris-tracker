<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LeadRejectedCustomersWidget extends BaseWidget
{
    protected static ?string $heading = 'Rejected Customers';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->role === 'lead';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::where('lead_id', auth()->id())
                    ->whereNotNull('rejected_at')
                    ->where('rep_acceptance_status', 'rejected')
                    ->orderBy('rejected_at', 'desc')
            )
            ->columns([
                TextColumn::make('customer_name')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->searchable(),
                TextColumn::make('city'),
                TextColumn::make('state'),
                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('rejectedBy.name')
                    ->label('Rejected By'),
                TextColumn::make('rejection_note')
                    ->limit(30),
                TextColumn::make('rejected_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('requestReplacement')
                    ->label('Request Replacement')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record) => ! $record->needs_replacement)
                    ->action(function ($record) {
                        $record->update([
                            'needs_replacement' => true,
                            'replacement_requested_by' => auth()->id(),
                            'replacement_requested_at' => now(),
                            'lead_id' => null,
                        ]);
                        $record->leads()->detach();
                    }),
            ]);
    }
}
