<?php

namespace App\Filament\Resources\RawMaterials\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RawMaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(200),

                TextInput::make('unit_of_measure')
                    ->label('Unit of Measure')
                    ->required()
                    ->maxLength(50)
                    ->placeholder('e.g. kg, litres, bags'),

                TextInput::make('quantity')
                    ->label('Current Stock Quantity')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->default(0)
                    ->step(0.0001)
                    ->rules(['decimal:0,4']),

                TextInput::make('reorder_level')
                    ->label('Reorder Level')
                    ->numeric()
                    ->minValue(0)
                    ->nullable()
                    ->step(0.0001)
                    ->rules(['nullable', 'decimal:0,4']),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
