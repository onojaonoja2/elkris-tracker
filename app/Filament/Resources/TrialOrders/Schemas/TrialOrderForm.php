<?php

namespace App\Filament\Resources\TrialOrders\Schemas;

use App\Models\ProductType;
use Filament\Forms\Components\FileUpload;
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
                    ->description('Log all physical products successfully picked up.')
                    ->schema([
                        Repeater::make('products')
                            ->schema([
                                Select::make('product_name')
                                    ->label('Product')
                                    ->options(fn () => ProductType::where('is_active', true)->pluck('name', 'name'))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('grammage', null)),

                                Select::make('grammage')
                                    ->label('Weight (g)')
                                    ->options(function (Get $get) {
                                        $productName = $get('product_name');
                                        if (! $productName) {
                                            return [];
                                        }
                                        $pt = ProductType::where('name', $productName)->first();
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
                                    ->minValue(0)
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

                FileUpload::make('receipt_path')
                    ->label('Upload Payment Receipt')
                    ->image()
                    ->maxSize(2048)
                    ->disk('s3')
                    ->directory('receipts/trial-orders')
                    ->visibility('private')
                    ->imageEditor()
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
