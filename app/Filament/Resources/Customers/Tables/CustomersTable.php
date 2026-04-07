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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
