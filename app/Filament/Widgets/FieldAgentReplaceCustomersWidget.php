<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class FieldAgentReplaceCustomersWidget extends BaseWidget
{
    protected static ?string $heading = 'Replace Customer';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user() && auth()->user()->role === 'field_agent';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::where('agent_id', auth()->id())
                    ->where('needs_replacement', true)
                    ->orderBy('replacement_requested_at', 'desc')
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
                TextColumn::make('rejection_note')
                    ->label('Rejection Reason')
                    ->limit(30),
                TextColumn::make('replacement_requested_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('replaceWithNew')
                    ->label('Replace with New Customer')
                    ->color('warning')
                    ->icon('heroicon-o-plus')
                    ->form([
                        TextInput::make('customer_name')
                            ->required(),
                        TextInput::make('phone_number')
                            ->required()
                            ->tel()
                            ->mask('99999999999')
                            ->rule('digits:11')
                            ->helperText('Must be exactly 11 digits'),
                        Textarea::make('address')
                            ->required(),
                        Select::make('priority')
                            ->options([
                                'high' => 'High',
                                'medium' => 'Medium',
                                'low' => 'Low',
                            ])
                            ->default('medium')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        // Mark old customer as replaced
                        $record->update([
                            'needs_replacement' => false,
                            'replacement_requested_by' => null,
                            'replacement_requested_at' => null,
                        ]);

                        // Create new customer
                        Customer::create([
                            'agent_id' => auth()->id(),
                            'customer_name' => $data['customer_name'],
                            'phone_number' => $data['phone_number'],
                            'address' => $data['address'],
                            'city' => $record->city,
                            'state' => $record->state,
                            'region' => $record->region,
                            'priority' => $data['priority'],
                            'customer_status' => 'customer',
                        ]);
                    }),
            ]);
    }
}
