<?php

namespace App\Filament\Resources\Stockists\Schemas;

use App\Filament\Resources\Customers\Schemas\CustomerForm;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

                Select::make('state')
                    ->options(function () {
                        $states = [];
                        foreach (CustomerForm::getCityMapping() as $data) {
                            $states[$data['state']] = $data['state'];
                        }

                        return array_unique($states);
                    })
                    ->searchable()
                    ->required()
                    ->live(debounce: 500)
                    ->afterStateUpdated(function (Set $set) {
                        $set('city', null);
                        $set('region', null);
                    }),

                Select::make('city')
                    ->options(function (callable $get) {
                        $state = $get('state');
                        if (! $state) {
                            return CustomerForm::nigerianCities();
                        }

                        $cities = [];
                        foreach (CustomerForm::getCityMapping() as $key => $data) {
                            if ($data['state'] === $state) {
                                $cities[$key] = $data['city'];
                            }
                        }

                        return $cities;
                    })
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) {
                        $map = CustomerForm::getCityMapping();
                        if (isset($map[$state])) {
                            $set('state', $map[$state]['state']);
                            $set('region', $map[$state]['region']);
                        }
                    }),

                Hidden::make('region'),

                Textarea::make('address')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }
}
