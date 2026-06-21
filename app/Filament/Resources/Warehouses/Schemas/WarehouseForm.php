<?php

namespace App\Filament\Resources\Warehouses\Schemas;

use App\Models\City;
use App\Models\Lga;
use App\Models\State;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(200),

                Select::make('type')
                    ->options([
                        'central' => 'Central Warehouse',
                        'state' => 'State Warehouse',
                    ])
                    ->required()
                    ->default('state')
                    ->live(),

                TextInput::make('phone')
                    ->tel()
                    ->maxLength(20),

                Select::make('state_id')
                    ->label('State')
                    ->options(fn () => State::pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live(debounce: 300)
                    ->afterStateUpdated(function (Set $set) {
                        $set('lga_id', null);
                        $set('city_id', null);
                    }),

                Select::make('lga_id')
                    ->label('LGA')
                    ->options(fn (Get $get) => $get('state_id')
                        ? Lga::where('state_id', $get('state_id'))->pluck('name', 'id')
                        : [])
                    ->searchable()
                    ->live(debounce: 300)
                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null)),

                Select::make('city_id')
                    ->label('City')
                    ->options(fn (Get $get) => $get('lga_id')
                        ? City::where('lga_id', $get('lga_id'))->pluck('name', 'id')
                        : [])
                    ->searchable(),

                Textarea::make('address')
                    ->columnSpanFull(),

                Select::make('manager_id')
                    ->label('Warehouse Manager')
                    ->options(fn () => User::where('role', 'warehouse_manager')->orWhere('role', 'admin')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),

                Select::make('sales_person_id')
                    ->label('Sales Person')
                    ->options(fn () => User::where('role', 'sales')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
