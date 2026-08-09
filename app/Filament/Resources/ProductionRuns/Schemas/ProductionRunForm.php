<?php

namespace App\Filament\Resources\ProductionRuns\Schemas;

use App\Models\RawMaterial;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProductionRunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('raw_materials')
                    ->label('Raw Materials')
                    ->schema([
                        Select::make('raw_material_id')
                            ->label('Raw Material')
                            ->options(fn () => RawMaterial::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->live(),

                        TextInput::make('quantity_used')
                            ->label('Quantity Used')
                            ->numeric()
                            ->required()
                            ->minValue(0.0001)
                            ->step(0.0001)
                            ->rules(['decimal:0,4'])
                            ->hint(fn (Get $get): ?string => self::stockHint($get('raw_material_id')))
                            ->live(),
                    ])
                    ->minItems(1)
                    ->addActionLabel('Add Raw Material')
                    ->defaultItems(1)
                    ->columns(2)
                    ->collapsible(),

                DatePicker::make('production_date')
                    ->label('Production Date')
                    ->required()
                    ->default(now()),

                TextInput::make('output_name')
                    ->label('Output Name')
                    ->required()
                    ->maxLength(200),

                TextInput::make('output_quantity')
                    ->label('Output Quantity')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->step(0.0001)
                    ->rules(['decimal:0,4']),

                TextInput::make('output_unit')
                    ->label('Output Unit')
                    ->required()
                    ->maxLength(50)
                    ->placeholder('e.g. units, cartons, bags'),

                Textarea::make('notes')
                    ->label('Production Notes')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }

    protected static function stockHint(?int $rawMaterialId): ?string
    {
        if (! $rawMaterialId) {
            return null;
        }

        $material = RawMaterial::find($rawMaterialId);

        if (! $material) {
            return null;
        }

        return "Available: {$material->quantity} {$material->unit_of_measure}";
    }
}
