<?php

namespace App\Filament\Resources\SalesRecords\Schemas;

use App\Models\Customer;
use App\Models\ProductType;
use App\Models\SalesRecord;
use App\Support\WarehouseOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SalesRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('vendor_name')
                    ->label('Market / Vendor Name')
                    ->visible(fn () => auth()->user()->hasRole('open_market'))
                    ->required(fn () => auth()->user()->hasRole('open_market'))
                    ->maxLength(255),

                TextInput::make('business_name')
                    ->label('Business Name (Restaurant / Hotel / Supermarket)')
                    ->visible(fn () => auth()->user()->hasRole('retail_market'))
                    ->required(fn () => auth()->user()->hasRole('retail_market'))
                    ->maxLength(255),

                Toggle::make('is_credit')
                    ->label('Credit Sale?')
                    ->helperText('Toggle on if the customer will pay later')
                    ->default(false)
                    ->live()
                    ->columnSpanFull(),

                Radio::make('stock_source')
                    ->label('Stock Source')
                    ->options([
                        'warehouse' => 'Request stock from warehouse',
                        'held' => 'Deduct from stock at hand',
                    ])
                    ->default('warehouse')
                    ->helperText('Choose whether this sale should be fulfilled from your held stock or requested from the warehouse.')
                    ->live()
                    ->required(fn () => auth()->user()->hasAnyRole(['open_market', 'retail_market']))
                    ->visible(fn () => auth()->user()->hasAnyRole(['open_market', 'retail_market']))
                    ->disabled(fn (?SalesRecord $record) => $record !== null)
                    ->columnSpanFull(),

                Select::make('warehouse_id')
                    ->label('Fulfilling Warehouse')
                    ->helperText('Stock will be allocated from this warehouse upon approval.')
                    ->options(fn () => WarehouseOptions::for())
                    ->searchable()
                    ->required(fn (Get $get): bool => auth()->user()->hasAnyRole(['open_market', 'retail_market']) && $get('stock_source') === 'warehouse')
                    ->visible(fn (Get $get): bool => auth()->user()->hasRole('admin')
                        || (auth()->user()->hasAnyRole(['open_market', 'retail_market']) && $get('stock_source') === 'warehouse'))
                    ->columnSpanFull(),

                Section::make('Customer Information')
                    ->description('Required for credit sales, optional for paid sales.')
                    ->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'customer_name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if (! $state) {
                                    return;
                                }

                                $customer = Customer::find($state);
                                $set('customer_name', $customer?->customer_name);
                                $set('customer_phone', $customer?->phone_number);
                            })
                            ->visible(fn (Get $get) => (bool) $get('is_credit')),

                        TextInput::make('customer_name')
                            ->label('Customer Name')
                            ->required(fn (Get $get) => (bool) $get('is_credit'))
                            ->maxLength(255),

                        TextInput::make('customer_phone')
                            ->label('Customer Phone')
                            ->maxLength(50),

                        DatePicker::make('expected_collection_date')
                            ->label('Expected Collection Date')
                            ->required()
                            ->native(false)
                            ->minDate(now()->toDateString())
                            ->visible(fn (Get $get) => (bool) $get('is_credit')),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('Products Sold')
                    ->description('Add the products sold in this transaction.')
                    ->schema([
                        Repeater::make('products')
                            ->schema([
                                Select::make('product_name')
                                    ->label('Product')
                                    ->options(fn () => ProductType::where('is_active', true)->pluck('name', 'name'))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $set('grammage', null);
                                        $set('cartons', 0);
                                        $set('pieces', 0);
                                        self::recalculateQuantity($set, $get);
                                    }),

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
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::recalculateQuantity($set, $get)),

                                TextInput::make('cartons')
                                    ->label('Cartons')
                                    ->numeric()
                                    ->integer()
                                    ->default(0)
                                    ->minValue(0)
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::recalculateQuantity($set, $get)),

                                TextInput::make('pieces')
                                    ->label('Units')
                                    ->numeric()
                                    ->integer()
                                    ->default(0)
                                    ->minValue(0)
                                    ->rules(fn (Get $get): array => ((int) $get('cartons') + (int) $get('pieces')) < 1 ? ['min:1'] : [])
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::recalculateQuantity($set, $get)),

                                TextInput::make('quantity')
                                    ->label('Total Pieces')
                                    ->numeric()
                                    ->integer()
                                    ->readOnly()
                                    ->default(1)
                                    ->minValue(1),

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
                            ->columns(7)
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalculateTotalPrice($set, $get))
                            ->deleteAction(fn ($action) => $action->after(fn (Set $set, Get $get) => self::recalculateTotalPrice($set, $get)))
                            ->reorderable(false),
                    ])
                    ->columnSpanFull(),

                FileUpload::make('receipt_path')
                    ->label('Upload Payment Receipt / Slip')
                    ->image()
                    ->maxSize(2048)
                    ->disk('s3')
                    ->directory('receipts/sales-records')
                    ->visibility('private')
                    ->imageEditor()
                    ->required(fn (Get $get) => ! (bool) $get('is_credit'))
                    ->visible(fn (Get $get) => ! (bool) $get('is_credit'))
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

    private static function getCartonQuantity(Get $get): int
    {
        $productName = $get('product_name');
        $grammage = (int) ($get('grammage') ?? 0);

        if (! $productName || $grammage <= 0) {
            return 1;
        }

        return ProductType::where('name', $productName)->first()?->cartonQuantityFor($grammage) ?? 1;
    }

    private static function recalculateQuantity(Set $set, Get $get): void
    {
        $cartons = (int) ($get('cartons') ?? 0);
        $pieces = (int) ($get('pieces') ?? 0);
        $set('quantity', $cartons * self::getCartonQuantity($get) + $pieces);
        self::recalculateLineTotal($set, $get);
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
