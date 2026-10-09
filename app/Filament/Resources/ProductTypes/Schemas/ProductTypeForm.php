<?php

namespace App\Filament\Resources\ProductTypes\Schemas;

use App\Models\ProductType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
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
                            ->required()
                            ->live(),

                        Select::make('packaging')
                            ->label('Packaging')
                            ->options([
                                'carton' => 'Carton',
                                'bag' => 'Bag',
                            ])
                            ->default('carton')
                            ->required()
                            ->live(),

                        TextInput::make('carton_quantity')
                            ->label(fn (Get $get): string => (($get('packaging') ?? 'carton') === 'bag' ? 'Pieces per Bag' : 'Pieces per Carton'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),

                        Toggle::make('display_unit')
                            ->label('Display in kg')
                            ->helperText('Weights of 1000g and above can be shown in kilograms.')
                            ->visible(fn (Get $get): bool => (int) ($get('grammage') ?? 0) >= 1000)
                            ->formatStateUsing(fn (?string $state): bool => $state === 'kg')
                            ->dehydrateStateUsing(fn (bool $state): string => $state ? 'kg' : 'g'),
                    ])
                    ->itemLabel(fn (array $state): ?string => isset($state['grammage'])
                        ? ProductType::formatGrammageEntry($state).' ('.(int) ($state['carton_quantity'] ?? 1).' pcs/'.(($state['packaging'] ?? 'carton') === 'bag' ? 'bag' : 'carton').')'
                        : null)
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
