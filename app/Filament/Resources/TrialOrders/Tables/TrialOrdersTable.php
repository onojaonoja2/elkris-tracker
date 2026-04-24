<?php

namespace App\Filament\Resources\TrialOrders\Tables;

use App\Models\Stockist;
use App\Models\StockistTransaction;
use Filament\Actions\Action;
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
                TextColumn::make('agent.assigned_cities')
                    ->label('City')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->toggleable(),
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
                Action::make('approveStock')
                    ->label('Approve Stock')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending' && in_array(auth()->user()->role, ['supervisor', 'sales', 'admin']))
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => auth()->id(),
                        ]);

                        $agent = $record->agent;
                        $agentCity = is_array($agent->assigned_cities) ? ($agent->assigned_cities[0] ?? null) : null;

                        if ($agent && $agentCity) {
                            $agent->increment('stock_balance', $record->total_value);

                            $stockist = Stockist::where('city', $agentCity)->first();
                            if ($stockist) {
                                $stockist->decrement('stock_balance', $record->total_value);

                                StockistTransaction::create([
                                    'stockist_id' => $stockist->id,
                                    'user_id' => auth()->id(),
                                    'field_agent_id' => $agent->id,
                                    'trial_order_id' => $record->id,
                                    'type' => 'deducted',
                                    'amount' => $record->total_value,
                                    'description' => 'Trial order stock deduction for field agent: '.$agent->name,
                                    'transaction_date' => now()->toDateString(),
                                ]);
                            }
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Stock Requisition')
                    ->modalDescription('Are you absolutely sure? This will irrevocably bind this total financial liability directly onto the field agent\'s active stock balance.'),
            ]);
    }
}
