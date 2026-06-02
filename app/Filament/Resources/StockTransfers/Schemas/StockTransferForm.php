<?php

namespace App\Filament\Resources\StockTransfers\Schemas;

use App\Models\ProductType;
use App\Models\Stockist;
use App\Models\Warehouse;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StockTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('from_warehouse_id')
                    ->label('From Warehouse')
                    ->options(fn () => Warehouse::pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live(),

                Select::make('to_warehouse_id')
                    ->label('To Warehouse')
                    ->options(fn () => Warehouse::pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->live(),

                Select::make('to_stockist_id')
                    ->label('To Stockist')
                    ->options(fn () => Stockist::pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->live(),

                Textarea::make('notes')
                    ->columnSpanFull(),

                Repeater::make('items')
                    ->label('Stock Items')
                    ->relationship('items')
                    ->schema([
                        Select::make('product_type_id')
                            ->label('Product')
                            ->options(fn () => ProductType::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('grammage', null)),

                        Select::make('grammage')
                            ->label('Weight (g)')
                            ->options(function (Get $get) {
                                $ptId = $get('product_type_id');
                                if (! $ptId) {
                                    return [];
                                }
                                $pt = ProductType::find($ptId);
                                if (! $pt) {
                                    return [];
                                }

                                return collect($pt->available_grammages)
                                    ->map(fn ($g) => is_array($g) ? $g['grammage'] : $g)
                                    ->mapWithKeys(fn ($g) => [(string) $g => $g.'g'])
                                    ->toArray();
                            })
                            ->required()
                            ->live(),

                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['product_type_id']
                        ? (ProductType::find($state['product_type_id'])?->name ?? '').' - '.($state['grammage'] ?? '').'g'
                        : null)
                    ->addActionLabel('Add Item')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
