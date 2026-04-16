<?php

namespace App\Filament\Resources\TrialOrders\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TrialOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('agent.name')
                    ->label('Field Agent')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_value')
                    ->label('Total Value (₦)')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->placeholder('N/A')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Date Requested')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                \Filament\Actions\Action::make('approveStock')
                    ->label('Approve Stock')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending' && in_array(auth()->user()->role, ['supervisor', 'sales', 'admin']))
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => auth()->id(),
                        ]);

                        // Add the approved total directly natively onto the reporting agent's active liability stock balance
                        $agent = $record->agent;
                        if ($agent) {
                            $agent->increment('stock_balance', $record->total_value);
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Stock Requisition')
                    ->modalDescription('Are you absolutely sure? This will irrevocably bind this total financial liability directly onto the field agent\'s active stock balance.'),
            ]);
    }
}
