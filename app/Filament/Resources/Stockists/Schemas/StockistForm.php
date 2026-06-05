<?php

namespace App\Filament\Resources\Stockists\Schemas;

use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Models\Lga;
use App\Models\State;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class StockistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('phone')
                    ->tel()
                    ->maxLength(11)
                    ->regex('/^[0-9]{11}$/')
                    ->validationMessages([
                        'regex' => 'The phone number must be exactly 11 numeric digits.',
                    ]),

                TextInput::make('email')
                    ->label('Login Email')
                    ->email()
                    ->required()
                    ->unique('users', 'email'),

                TextInput::make('password')
                    ->label('Login Password')
                    ->password()
                    ->required()
                    ->hiddenOn('edit'),

                Select::make('state_id')
                    ->label('State')
                    ->options(fn () => State::pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live(debounce: 300)
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
                    ->live(),

                TextInput::make('city')
                    ->label('City')
                    ->datalist(function (Get $get) {
                        $stateId = $get('state_id');

                        $cities = collect(CustomerForm::getCityMapping());

                        if ($stateId) {
                            $stateName = State::find($stateId)?->name;
                            $cities = $stateName
                                ? $cities->where('state', $stateName)
                                : collect();
                        }

                        return $cities->pluck('city')->unique()->toArray();
                    })
                    ->required(),

                Hidden::make('state'),
                Hidden::make('region'),

                Textarea::make('address')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }
}
