<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 1. LEAD SELECTION: Visible only to Admin
                MultiSelect::make('leads')
                    ->label('Assigned Leads')
                    ->relationship('leads', 'name', fn ($query) => $query->where('role', 'lead'))
                    ->searchable()
                    ->required(fn (): bool => auth()->user()->role === 'admin')
                    ->visible(fn (): bool => auth()->user()->role === 'admin'),

                // 2. REP SELECTION: Visible only to Admin
                MultiSelect::make('reps')
                    ->label('Assigned Reps')
                    ->relationship('reps', 'name', fn ($query) => $query->where('role', 'rep'))
                    ->searchable()
                    ->required(fn (): bool => auth()->user()->role === 'admin')
                    ->visible(fn (): bool => auth()->user()->role === 'admin'),

                TextInput::make('customer_name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('phone_number')
                    ->tel()
                    ->required(),

                TextInput::make('age')
                    ->numeric(),

                Select::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ]),

                Select::make('city')
                    ->options(self::nigerianCities())
                    ->searchable()
                    ->required(),

                Textarea::make('address')
                    ->columnSpanFull(),

                Select::make('customer_status')
                    ->options([
                        'prospect' => 'Prospect',
                        'customer' => 'Customer',
                    ])
                    ->required(),

                Select::make('diabetic_awareness')
                    ->options([
                        'yes' => 'Yes',
                        'no' => 'No',
                        'unknown' => 'Unknown',
                    ]),

                DatePicker::make('call_date')
                    ->native(false)
                    ->displayFormat('d/m/Y'),

                TextInput::make('preffered_call_time'),

                Textarea::make('feedback')
                    ->rows(3)
                    ->columnSpanFull(),

                Textarea::make('remarks')
                    ->rows(3)
                    ->columnSpanFull(),

                DatePicker::make('follow_up_date')
                    ->native(false)
                    ->displayFormat('d/m/Y'),

                Select::make('trial_order_purchase')
                    ->label('Trail Order Purchase?')
                    ->options([
                        'yes' => 'Yes',
                        'no' => 'No',
                    ])
                    ->live()
                    ->visible(fn() => auth()->user()->role === 'field_agent'),

                // ── Products Section ──
                Section::make('Products')
                    ->visible(fn(Get $get) => auth()->user()->role !== 'field_agent' || (auth()->user()->role === 'field_agent' && $get('trial_order_purchase') === 'yes'))
                    ->schema([
                        Repeater::make('products')
                            ->relationship()
                            ->schema([
                                TextInput::make('product_name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('grammage')
                                    ->label('Grammage (g)')
                                    ->numeric()
                                    ->suffix('g')
                                    ->required()
                                    ->default(0),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live(debounce: 300)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::recalculateLineTotal($set, $get)),
                                TextInput::make('price')
                                    ->label('Unit Price (₦)')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->required()
                                    ->default(0)
                                    ->live(debounce: 300)
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
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Select::make('preferred_payment_option')
                    ->label('Preferred Payment Option')
                    ->visible(fn() => auth()->user()->role !== 'field_agent')
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
                    ->visible(fn() => auth()->user()->role !== 'field_agent')
                    ->default(0),

                TextInput::make('order_quantity')
                    ->numeric()
                    ->default(0)
                    ->prefix('Qty:')
                    ->visible(fn() => auth()->user()->role !== 'field_agent'),

                Textarea::make('delivery_details')
                    ->visible(fn() => auth()->user()->role !== 'field_agent')
                    ->columnSpanFull(),

                Select::make('delivery_status')
                    ->visible(fn() => auth()->user()->role !== 'field_agent')
                    ->options([
                        'pending' => 'Pending',
                        'dispatched' => 'Dispatched',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ]),
            ]);
    }

    /**
     * Recalculate the line total for a single product row.
     */
    private static function recalculateLineTotal(Set $set, Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 1);
        $price = (float) ($get('price') ?? 0);
        $set('line_total', $quantity * $price);

        // Also recalculate the grand total
        self::recalculateTotalPrice($set, $get);
    }

    /**
     * Recalculate the grand total price across all product rows.
     */
    private static function recalculateTotalPrice(Set $set, Get $get): void
    {
        $products = $get('../../products') ?? [];
        $total = 0;
        foreach ($products as $product) {
            $qty = (float) ($product['quantity'] ?? 1);
            $price = (float) ($product['price'] ?? 0);
            $total += $qty * $price;
        }
        $set('../../total_price', $total);
    }

    /**
     * 82 Nigerian cities and urban areas.
     *
     * @return array<string, string>
     */
    public static function nigerianCities(): array
    {
        return [
            // FCT
            'abuja' => 'Abuja',
            'gwagwalada' => 'Gwagwalada',
            'kuje' => 'Kuje',
            // Lagos
            'lagos' => 'Lagos (Mainland)',
            'ikeja' => 'Ikeja',
            'lekki' => 'Lekki',
            'ajah' => 'Ajah',
            'ikorodu' => 'Ikorodu',
            'badagry' => 'Badagry',
            'epe' => 'Epe',
            'vi' => 'Victoria Island',
            'ikoyi' => 'Ikoyi',
            'surulere' => 'Surulere',
            'agege' => 'Agege',
            'oshodi' => 'Oshodi',
            'yaba' => 'Yaba',
            // Oyo
            'ibadan' => 'Ibadan',
            'oyo' => 'Oyo',
            'ogbomoso' => 'Ogbomoso',
            // Rivers
            'port_harcourt' => 'Port Harcourt',
            // Kano
            'kano' => 'Kano',
            // Kaduna
            'kaduna' => 'Kaduna',
            'zaria' => 'Zaria',
            // Enugu
            'enugu' => 'Enugu',
            // Anambra
            'onitsha' => 'Onitsha',
            'awka' => 'Awka',
            'nnewi' => 'Nnewi',
            // Delta
            'warri' => 'Warri',
            'asaba' => 'Asaba',
            'sapele' => 'Sapele',
            // Edo
            'benin' => 'Benin City',
            // Ogun
            'abeokuta' => 'Abeokuta',
            'sagamu' => 'Sagamu',
            'ota' => 'Ota',
            'ijebu_ode' => 'Ijebu Ode',
            // Osun
            'osogbo' => 'Osogbo',
            'ife' => 'Ile-Ife',
            // Ondo
            'akure' => 'Akure',
            'ondo' => 'Ondo',
            // Ekiti
            'ado_ekiti' => 'Ado-Ekiti',
            // Kwara
            'ilorin' => 'Ilorin',
            // Plateau
            'jos' => 'Jos',
            // Benue
            'makurdi' => 'Makurdi',
            // Cross River
            'calabar' => 'Calabar',
            // Akwa Ibom
            'uyo' => 'Uyo',
            // Imo
            'owerri' => 'Owerri',
            // Abia
            'umuahia' => 'Umuahia',
            'aba' => 'Aba',
            // Borno
            'maiduguri' => 'Maiduguri',
            // Sokoto
            'sokoto' => 'Sokoto',
            // Katsina
            'katsina' => 'Katsina',
            // Bauchi
            'bauchi' => 'Bauchi',
            // Adamawa
            'yola' => 'Yola',
            'jimeta' => 'Jimeta',
            // Niger
            'minna' => 'Minna',
            'suleja' => 'Suleja',
            // Taraba
            'jalingo' => 'Jalingo',
            // Nasarawa
            'lafia' => 'Lafia',
            // Kogi
            'lokoja' => 'Lokoja',
            'okene' => 'Okene',
            // Gombe
            'gombe' => 'Gombe',
            // Yobe
            'damaturu' => 'Damaturu',
            // Ebonyi
            'abakaliki' => 'Abakaliki',
            // Bayelsa
            'yenagoa' => 'Yenagoa',
            // Jigawa
            'dutse' => 'Dutse',
            'hadejia' => 'Hadejia',
            // Kebbi
            'birnin_kebbi' => 'Birnin Kebbi',
            // Zamfara
            'gusau' => 'Gusau',
            // Kebbi
            'argungu' => 'Argungu',
            // Osun
            'ilesa' => 'Ilesa',
            // Oyo
            'oyo_town' => 'Oyo Town',
            // Lagos
            'alimosho' => 'Alimosho',
            'ifako_ijaiye' => 'Ifako-Ijaiye',
            'mushin' => 'Mushin',
            'oshodi_isolo' => 'Oshodi-Isolo',
            'amukpe' => 'Amukpe',
            // Rivers
            'bonny' => 'Bonny',
            'eleme' => 'Eleme',
            // Delta
            'ughelli' => 'Ughelli',
            // Anambra
            'agulu' => 'Agulu',
            // Imo
            'orlu' => 'Orlu',
            'okigwe' => 'Okigwe',
        ];
    }
}
