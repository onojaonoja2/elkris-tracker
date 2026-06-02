<?php

namespace App\Filament\Resources\ProductTypes\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(200)
                    ->unique(ignoreRecord: true),

                Repeater::make('available_grammages')
                    ->label('Available Weights (Grammage)')
                    ->schema([
                        TextInput::make('grammage')
                            ->label('Weight (g)')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['grammage'] ?? null)
                    ->addActionLabel('Add Weight')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->required(),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
