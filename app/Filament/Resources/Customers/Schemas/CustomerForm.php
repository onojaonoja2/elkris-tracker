<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Lga;
use App\Models\State;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 1. LEAD SELECTION: Visible only to Admin
                MultiSelect::make('leads')
                    ->label('Assigned Leads')
                    ->relationship('leads', 'name', fn ($query) => $query->where('role', 'lead'))
                    ->searchable()
                    ->required(fn (): bool => auth()->user()->role === 'admin')
                    ->visible(fn (): bool => auth()->user()->role === 'admin'),

                // 2. REP SELECTION: Visible only to Admin
                MultiSelect::make('reps')
                    ->label('Assigned Reps')
                    ->relationship('reps', 'name', fn ($query) => $query->where('role', 'rep'))
                    ->searchable()
                    ->required(fn (): bool => auth()->user()->role === 'admin')
                    ->visible(fn (): bool => auth()->user()->role === 'admin'),

                TextInput::make('customer_name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('phone_number')
                    ->tel()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(11)
                    ->regex('/^[0-9]{11}$/')
                    ->validationMessages([
                        'regex' => 'The phone number must be exactly 11 numeric digits without spaces or dashes.',
                    ]),

                TextInput::make('age')
                    ->numeric()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                Select::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ])
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                Select::make('state_id')
                    ->label('State')
                    ->options(fn () => State::pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live(debounce: 300)
                    ->visible(fn () => auth()->user()->role !== 'field_agent')
                    ->afterStateUpdated(function (Set $set, $stateId) {
                        $set('lga_id', null);
                        $state = State::find($stateId);
                        $set('state', $state?->name);
                        $set('region', $state?->region?->name);
                    }),

                Select::make('lga_id')
                    ->label('Local Government Area')
                    ->options(fn (Get $get) => $get('state_id')
                        ? Lga::where('state_id', $get('state_id'))->pluck('name', 'id')
                        : [])
                    ->searchable()
                    ->required()
                    ->live()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                TextInput::make('city')
                    ->required()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                Hidden::make('region'),

                Textarea::make('address')
                    ->required(fn () => auth()->user()->role === 'field_agent')
                    ->columnSpanFull(),

                Checkbox::make('assign_to_self')
                    ->label('Assign to myself (add to my portfolio)')
                    ->visible(fn () => auth()->user()->role === 'lead')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($state) {
                            $set('agent_id', auth()->id());
                            $set('lead_id', auth()->id());
                            $set('leads', [auth()->id()]);
                        }
                    }),

                Hidden::make('agent_id'),

                Hidden::make('lead_id'),

                Hidden::make('customer_status')
                    ->default('customer'),

                Select::make('priority')
                    ->label('Customer Priority')
                    ->options([
                        'high' => 'High',
                        'medium' => 'Medium',
                        'low' => 'Low',
                    ])
                    ->default('medium')
                    ->required(),

                Select::make('diabetic_awareness')
                    ->options([
                        'yes' => 'Yes',
                        'no' => 'No',
                        'unknown' => 'Unknown',
                    ])
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                DatePicker::make('call_date')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                TextInput::make('preffered_call_time')
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                Textarea::make('feedback')
                    ->rows(3)
                    ->columnSpanFull()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                Textarea::make('remarks')
                    ->rows(3)
                    ->columnSpanFull()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                DatePicker::make('follow_up_date')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                KeyValue::make('lifetime_purchases')
                    ->label('Lifetime Purchases')
                    ->keyLabel('Product & Grammage')
                    ->valueLabel('Total Quantity')
                    ->disabled() // Read-only tally
                    ->columnSpanFull()
                    ->visible(fn () => auth()->user()->role !== 'field_agent'),

                // Products and order details are now handled via the OrdersRelationManager
            ]);
    }

    public static function getCityMapping(): array
    {
        return config('locations.cities', []);
    }
}
