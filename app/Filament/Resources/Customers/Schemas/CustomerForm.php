<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

                Select::make('city')
                    ->options(self::nigerianCities())
                    ->searchable()
                    ->required()
                    ->default(fn () => self::getDefaultCity())
                    ->live(debounce: 500)
                    ->visible(fn () => auth()->user()->role !== 'field_agent')
                    ->afterStateUpdated(function (Set $set, $state) {
                        $map = self::getCityMapping();
                        if (isset($map[$state])) {
                            $set('state', $map[$state]['state']);
                            $set('region', $map[$state]['region']);
                        } else {
                            $set('state', null);
                            $set('region', null);
                        }
                    }),

                Hidden::make('state')
                    ->default(function () {
                        $city = self::getDefaultCity();

                        return $city ? (self::getCityMapping()[$city]['state'] ?? null) : null;
                    }),

                Hidden::make('region')
                    ->default(function () {
                        $city = self::getDefaultCity();

                        return $city ? (self::getCityMapping()[$city]['region'] ?? null) : null;
                    }),

                Textarea::make('address')
                    ->required(fn () => auth()->user()->role === 'field_agent')
                    ->columnSpanFull(),

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

    /**
     * Extracts the fallback default city for field agents.
     */
    public static function getDefaultCity(): ?string
    {
        $user = auth()->user();
        if ($user && $user->role === 'field_agent' && ! empty($user->assigned_cities)) {
            return is_array($user->assigned_cities) ? $user->assigned_cities[0] : null;
        }

        return null;
    }

    /**
     * 82 Nigerian cities and urban areas.
     *
     * @return array<string, string>
     */
    public static function nigerianCities(): array
    {
        $options = [];
        foreach (self::getCityMapping() as $key => $data) {
            $options[$data['state']][$key] = $data['city'];
        }

        return $options;
    }

    public static function getCityMapping(): array
    {
        return config('locations.cities', []);
    }
}
