<?php

namespace App\Filament\Resources\TrialOrders\Schemas;

use App\Models\StockistStock;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TrialOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Stock Details')
                    ->description('Log all physical products successfully picked up from the main stockist.')
                    ->schema([
                        Repeater::make('products')
                            ->schema([
                                Select::make('product_name')
                                    ->options([
                                        'Elkris Oat Flour' => 'Elkris Oat Flour',
                                        'Elkris Plantain' => 'Elkris Plantain',
                                        'Elkris Poundo Yam' => 'Elkris Poundo Yam',
                                    ])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('grammage', null)),
                                Select::make('grammage')
                                    ->label('Grammage (g)')
                                    ->options(fn (Get $get): array => match ($get('product_name')) {
                                        'Elkris Oat Flour' => ['5000' => '5000g', '1300' => '1300g', '650' => '650g'],
                                        'Elkris Plantain' => ['1800' => '1800g', '900' => '900g'],
                                        'Elkris Poundo Yam' => ['1800' => '1800g'],
                                        default => [],
                                    })
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        $productName = $get('product_name');
                                        if ($productName && $state) {
                                            $stock = StockistStock::where('product_name', $productName)
                                                ->where('grammage', $state)
                                                ->orderBy('created_at', 'desc')
                                                ->first();
                                            if ($stock && $stock->unit_price > 0) {
                                                $set('price', $stock->unit_price);
                                                self::recalculateLineTotal($set, $get);
                                            }
                                        }
                                    }),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::recalculateLineTotal($set, $get)),
                                TextInput::make('price')
                                    ->label('Unit Price (₦)')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->required()
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::recalculateLineTotal($set, $get)),
                                TextInput::make('line_total')
                                    ->label('Line Total (₦)')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default(0),
                            ])
                            ->columns(5)
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalculateTotalPrice($set, $get))
                            ->deleteAction(fn ($action) => $action->after(fn (Set $set, Get $get) => self::recalculateTotalPrice($set, $get)))
                            ->reorderable(false),
                    ])
                    ->columnSpanFull(),

                TextInput::make('total_value')
                    ->label('Total Value (₦)')
                    ->numeric()
                    ->prefix('₦')
                    ->readOnly()
                    ->default(0)
                    ->columnSpanFull(),
            ]);
    }

    private static function recalculateLineTotal(Set $set, Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 1);
        $price = (float) ($get('price') ?? 0);
        $set('line_total', $quantity * $price);
        self::recalculateTotalPrice($set, $get);
    }

    private static function recalculateTotalPrice(Set $set, Get $get): void
    {
        $products = $get('../../products') ?? [];
        $total = 0;
        foreach ($products as $product) {
            $qty = (float) ($product['quantity'] ?? 1);
            $price = (float) ($product['price'] ?? 0);
            $total += $qty * $price;
        }
        $set('../../total_value', $total);
    }
}
