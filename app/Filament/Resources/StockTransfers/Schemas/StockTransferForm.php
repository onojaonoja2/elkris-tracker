<?php

namespace App\Filament\Resources\StockTransfers\Schemas;

use App\Models\ProductType;
use App\Models\User;
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
                    ->nullable()
                    ->live(),

                Select::make('from_agent_id')
                    ->label('Collect From Agent')
                    ->options(function () {
                        $user = auth()->user();

                        $query = User::where('is_active', true)
                            ->whereIn('role', ['community_sales_representative', 'open_market', 'retail_market']);

                        if ($user->role === 'supervisor') {
                            $query->where(function ($q) use ($user) {
                                $q->where('lead_id', $user->id)
                                    ->orWhere('portfolio_agent_id', $user->id);
                            });
                        }

                        return $query->pluck('name', 'id');
                    })
                    ->searchable()
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(fn ($set, $state) => $state
                        ? $set('from_warehouse_id', null)
                        : null),

                Select::make('to_warehouse_id')
                    ->label('To Warehouse')
                    ->options(fn () => Warehouse::pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->live(),

                Select::make('to_agent_id')
                    ->label('To Agent')
                    ->options(function () {
                        $user = auth()->user();

                        $query = User::where('is_active', true)
                            ->whereIn('role', ['community_sales_representative', 'open_market', 'retail_market']);

                        if ($user->role === 'supervisor') {
                            $query->where(function ($q) use ($user) {
                                $q->where('lead_id', $user->id)
                                    ->orWhere('portfolio_agent_id', $user->id);
                            });
                        }

                        return $query->pluck('name', 'id');
                    })
                    ->searchable()
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(fn ($set, $state) => $state
                        ? $set('from_warehouse_id', null)
                        : null),

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
