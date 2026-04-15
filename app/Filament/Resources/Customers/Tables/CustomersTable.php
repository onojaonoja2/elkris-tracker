<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // 1. LEAD INFORMATION
                // TextColumn::make('lead_id')
                //     ->numeric()
                //     ->sortable(),
                TextColumn::make('lead.name')
                    ->label('Lead Name')
                    // ->description(fn($record): string => "ID: " . ($record->lead->my_id ?? 'N/A')) // Shows internal ID underneath
                    ->sortable()
                    ->searchable(),

                // 2. REP INFORMATION
                // TextColumn::make('rep_id')
                //     ->numeric()
                //     ->sortable()
                //     ->searchable(),

                TextColumn::make('rep.name')
                    ->label('Rep Name')
                    // ->description(fn($record): string => "ID: " . ($record->rep->my_id ?? 'N/A')) // Shows internal ID underneath
                    ->sortable()
                    ->searchable(),

                TextColumn::make('rep.my_id')
                    ->label('Rep Internal ID')
                    ->formatStateUsing(fn ($state): string => 'rep-' . $state)
                    ->sortable()
                    ->searchable(query: function ($query, $search) {
                        $search = preg_replace('/^rep-/', '', $search);
                        return $query->whereHas('rep', function ($q) use ($search) {
                            $q->where('my_id', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('customer_name')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->searchable(),
                TextColumn::make('age')
                    // ->sortable()
                    ->numeric(),
                TextColumn::make('gender'),
                // ->searchable(),
                TextColumn::make('city')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer_status')
                    ->searchable(),
                TextColumn::make('diabetic_awareness')
                    ->searchable(),
                TextColumn::make('call_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('preffered_call_time')
                    ->searchable(),
                TextColumn::make('follow_up_date')
                    ->searchable(),
                // TextColumn::make('order_quantity')
                //     ->numeric()
                //     ->sortable(),
                TextColumn::make('delivery_status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('call_date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(
                        fn($query, array $data) => $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('call_date', '>=', $date))
                            ->when($data['until'], fn($q, $date) => $q->whereDate('call_date', '<=', $date))
                    )
            ])
            ->recordActions([
                \Filament\Actions\Action::make('assignToRep')
                    ->label('Assign')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn ($record) => in_array(auth()->user()->role, ['admin', 'manager', 'lead']) && $record->agent_id !== null)
                    ->form([
                        \Filament\Forms\Components\Select::make('rep_id')
                            ->label('Select Rep')
                            ->options(\App\Models\User::where('role', 'rep')->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'rep_id' => $data['rep_id'],
                            'rep_acceptance_status' => 'pending',
                        ]);
                        $record->reps()->syncWithoutDetaching([$data['rep_id']]);
                    }),

                \Filament\Actions\Action::make('acceptLead')
                    ->label('Accept')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn ($record) => auth()->user()->role === 'rep' && $record->rep_acceptance_status === 'pending' && $record->rep_id === auth()->id())
                    ->action(function ($record) {
                        $record->update(['rep_acceptance_status' => 'accepted']);
                    }),

                \Filament\Actions\Action::make('rejectLead')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn ($record) => auth()->user()->role === 'rep' && $record->rep_acceptance_status === 'pending' && $record->rep_id === auth()->id())
                    ->action(function ($record) {
                        $record->update(['rep_acceptance_status' => 'rejected']);
                    }),

                \Filament\Actions\Action::make('markDelivered')
                    ->label('Mark Delivered')
                    ->color('success')
                    ->icon('heroicon-o-truck')
                    ->visible(fn ($record) => auth()->user()->role === 'sales' && in_array($record->delivery_status, ['pending', 'dispatched']))
                    ->action(function ($record) {
                        $record->update(['delivery_status' => 'delivered']);
                    }),

                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make()
                    ->visible(fn() => auth()->user()->role !== 'sales'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
