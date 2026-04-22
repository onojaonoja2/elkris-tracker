<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\Order;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('preferred_payment_option')
                    ->label('Preferred Payment Option')
                    ->options([
                        'bank_transfer' => 'Bank Transfer',
                        'cash_on_delivery' => 'Cash on Delivery',
                        'pos' => 'POS',
                        'mobile_money' => 'Mobile Money',
                        'cheque' => 'Cheque',
                    ]),

                TextInput::make('total_price')
                    ->label('Total Price (₦)')
                    ->numeric()
                    ->prefix('₦')
                    ->readOnly()
                    ->default(0),

                DatePicker::make('preferred_delivery_date')
                    ->native(false)
                    ->displayFormat('d/m/Y'),

                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'dispatched' => 'Dispatched',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending')
                    ->required(),

                Textarea::make('delivery_details')
                    ->columnSpanFull(),

                Repeater::make('products')
                    ->relationship()
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
                            ->required(),
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
                    ->deleteAction(fn ($action) => $action->after(fn (Set $set, Get $get) => self::recalculateTotalPrice($set, $get)))
                    ->reorderable(false)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Order #'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'dispatched' => 'info',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_price')
                    ->money('NGN'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
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
        $products = $get('../../products');
        $isItemContext = is_array($products);
        
        if (! $isItemContext) {
            $products = $get('products') ?? [];
        }

        $newTotal = 0;
        foreach ($products as $product) {
            $qty = (float) ($product['quantity'] ?? 1);
            $price = (float) ($product['price'] ?? 0);
            $newTotal += $qty * $price;
        }

        if ($isItemContext) {
            $set('../../total_price', $newTotal);
        } else {
            $set('total_price', $newTotal);
        }
    }
}
