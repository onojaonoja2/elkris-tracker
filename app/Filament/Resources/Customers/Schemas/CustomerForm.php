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
                    ->required()
                    ->live(debounce: 500)
                    ->afterStateUpdated(function (Set $set, $state) {
                        $map = self::getCityMapping();
                        if (isset($map[$state])) {
                            $set('state', $map[$state]['state']);
                            $set('region', $map[$state]['region']);
                        } else {
                            $set('state', null);
                            $set('region', null);
                        }
                    }),

                \Filament\Forms\Components\Hidden::make('state'),
                \Filament\Forms\Components\Hidden::make('region'),

                Textarea::make('address')
                    ->columnSpanFull(),

                \Filament\Forms\Components\Hidden::make('customer_status')
                    ->default('customer'),

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
                    ->required()
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
        $options = [];
        foreach (self::getCityMapping() as $key => $data) {
            $options[$data['state']][$key] = $data['city'];
        }
        return $options;
    }

    public static function getCityMapping(): array
    {
        return [
            // South West
            'lagos_island' => ['city' => 'Lagos Island', 'state' => 'Lagos', 'region' => 'South West'],
            'ikorodu' => ['city' => 'Ikorodu', 'state' => 'Lagos', 'region' => 'South West'],
            'epe' => ['city' => 'Epe', 'state' => 'Lagos', 'region' => 'South West'],
            'ibadan' => ['city' => 'Ibadan', 'state' => 'Oyo', 'region' => 'South West'],
            'ogbomosho' => ['city' => 'Ogbomosho', 'state' => 'Oyo', 'region' => 'South West'],
            'oyo' => ['city' => 'Oyo', 'state' => 'Oyo', 'region' => 'South West'],
            'iseyin' => ['city' => 'Iseyin', 'state' => 'Oyo', 'region' => 'South West'],
            'shaki' => ['city' => 'Shaki', 'state' => 'Oyo', 'region' => 'South West'],
            'kisi' => ['city' => 'Kisi', 'state' => 'Oyo', 'region' => 'South West'],
            'igboho' => ['city' => 'Igboho', 'state' => 'Oyo', 'region' => 'South West'],
            'ife' => ['city' => 'Ife', 'state' => 'Osun', 'region' => 'South West'],
            'ilesa' => ['city' => 'Ilesa', 'state' => 'Osun', 'region' => 'South West'],
            'iwo' => ['city' => 'Iwo', 'state' => 'Osun', 'region' => 'South West'],
            'osogbo' => ['city' => 'Osogbo', 'state' => 'Osun', 'region' => 'South West'],
            'ila' => ['city' => 'Ila', 'state' => 'Osun', 'region' => 'South West'],
            'gbongan' => ['city' => 'Gbongan', 'state' => 'Osun', 'region' => 'South West'],
            'ilawe_ekiti' => ['city' => 'Ilawe Ekiti', 'state' => 'Ekiti', 'region' => 'South West'],
            'ise_ekiti' => ['city' => 'Ise Ekiti', 'state' => 'Ekiti', 'region' => 'South West'],
            'ijero_ekiti' => ['city' => 'Ijero Ekiti', 'state' => 'Ekiti', 'region' => 'South West'],
            'ado_ekiti' => ['city' => 'Ado Ekiti', 'state' => 'Ekiti', 'region' => 'South West'],
            'akure' => ['city' => 'Akure', 'state' => 'Ondo', 'region' => 'South West'],
            'ondo_city' => ['city' => 'Ondo', 'state' => 'Ondo', 'region' => 'South West'],
            'owo' => ['city' => 'Owo', 'state' => 'Ondo', 'region' => 'South West'],
            'ikare' => ['city' => 'Ikare', 'state' => 'Ondo', 'region' => 'South West'],
            'abeokuta' => ['city' => 'Abeokuta', 'state' => 'Ogun', 'region' => 'South West'],
            'sagamu' => ['city' => 'Sagamu', 'state' => 'Ogun', 'region' => 'South West'],
            'obafemi_owode' => ['city' => 'Obafemi Owode', 'state' => 'Ogun', 'region' => 'South West'],
            'ijebu_ode' => ['city' => 'Ijebu Ode', 'state' => 'Ogun', 'region' => 'South West'],

            // South South
            'benin_city' => ['city' => 'Benin City', 'state' => 'Edo', 'region' => 'South South'],
            'auchi' => ['city' => 'Auchi', 'state' => 'Edo', 'region' => 'South South'],
            'uromi' => ['city' => 'Uromi', 'state' => 'Edo', 'region' => 'South South'],
            'ekpoma' => ['city' => 'Ekpoma', 'state' => 'Edo', 'region' => 'South South'],
            'warri' => ['city' => 'Warri', 'state' => 'Delta', 'region' => 'South South'],
            'sapele' => ['city' => 'Sapele', 'state' => 'Delta', 'region' => 'South South'],
            'asaba' => ['city' => 'Asaba', 'state' => 'Delta', 'region' => 'South South'],
            'uyo' => ['city' => 'Uyo', 'state' => 'Akwa Ibom', 'region' => 'South South'],
            'ikot_ekpeme' => ['city' => 'Ikot Ekpeme', 'state' => 'Akwa Ibom', 'region' => 'South South'],
            'port_harcourt' => ['city' => 'Port Harcourt', 'state' => 'Rivers', 'region' => 'South South'],
            'buguma' => ['city' => 'Buguma', 'state' => 'Rivers', 'region' => 'South South'],
            'calabar' => ['city' => 'Calabar', 'state' => 'Cross River', 'region' => 'South South'],
            'ugeb' => ['city' => 'Ugeb', 'state' => 'Cross River', 'region' => 'South South'],

            // South East Zone
            'aba' => ['city' => 'Aba', 'state' => 'Abia', 'region' => 'South East Zone'],
            'umuahia' => ['city' => 'Umuahia', 'state' => 'Abia', 'region' => 'South East Zone'],
            'enugu' => ['city' => 'Enugu', 'state' => 'Enugu', 'region' => 'South East Zone'],
            'nsukka' => ['city' => 'Nsukka', 'state' => 'Enugu', 'region' => 'South East Zone'],
            'awka' => ['city' => 'Awka', 'state' => 'Anambra', 'region' => 'South East Zone'],
            'okpoko' => ['city' => 'Okpoko', 'state' => 'Anambra', 'region' => 'South East Zone'],
            'owerri' => ['city' => 'Owerri', 'state' => 'Imo', 'region' => 'South East Zone'],
            'okigwe' => ['city' => 'Okigwe', 'state' => 'Imo', 'region' => 'South East Zone'],
            'abakaliki' => ['city' => 'Abakaliki', 'state' => 'Ebonyi', 'region' => 'South East Zone'],

            // North Central Zone
            'minna' => ['city' => 'Minna', 'state' => 'Niger', 'region' => 'North Central Zone'],
            'mokwa' => ['city' => 'Mokwa', 'state' => 'Niger', 'region' => 'North Central Zone'],
            'lavun' => ['city' => 'Lavun', 'state' => 'Niger', 'region' => 'North Central Zone'],
            'bida' => ['city' => 'Bida', 'state' => 'Niger', 'region' => 'North Central Zone'],
            'suleja' => ['city' => 'Suleja', 'state' => 'Niger', 'region' => 'North Central Zone'],
            'ilorin' => ['city' => 'Ilorin', 'state' => 'Kwara', 'region' => 'North Central Zone'],
            'abuja' => ['city' => 'Abuja', 'state' => 'FCT', 'region' => 'North Central Zone'],
            'lafia' => ['city' => 'Lafia', 'state' => 'Nasarawa', 'region' => 'North Central Zone'],
            'makurdi' => ['city' => 'Makurdi', 'state' => 'Benue', 'region' => 'North Central Zone'],
            'gboko' => ['city' => 'Gboko', 'state' => 'Benue', 'region' => 'North Central Zone'],
            'otukpo' => ['city' => 'Otukpo', 'state' => 'Benue', 'region' => 'North Central Zone'],
            'okene' => ['city' => 'Okene', 'state' => 'Kogi', 'region' => 'North Central Zone'],

            // North West Zone
            'kano' => ['city' => 'Kano', 'state' => 'Kano', 'region' => 'North West Zone'],
            'zaria' => ['city' => 'Zaria', 'state' => 'Kaduna', 'region' => 'North West Zone'],
            'kaduna_city' => ['city' => 'Kaduna', 'state' => 'Kaduna', 'region' => 'North West Zone'],
            'sokoto' => ['city' => 'Sokoto', 'state' => 'Sokoto', 'region' => 'North West Zone'],
            'katsina_city' => ['city' => 'Katsina', 'state' => 'Katsina', 'region' => 'North West Zone'],
            'funtua' => ['city' => 'Funtua', 'state' => 'Katsina', 'region' => 'North West Zone'],
            'gusau' => ['city' => 'Gusau', 'state' => 'Zamfara', 'region' => 'North West Zone'],
            'garki' => ['city' => 'Garki', 'state' => 'Jigawa', 'region' => 'North West Zone'],

            // North East Zone
            'bauchi_city' => ['city' => 'Bauchi', 'state' => 'Bauchi', 'region' => 'North East Zone'],
            'maiduguri' => ['city' => 'Maiduguri', 'state' => 'Borno', 'region' => 'North East Zone'],
            'bama' => ['city' => 'Bama', 'state' => 'Borno', 'region' => 'North East Zone'],
            'yola' => ['city' => 'Yola', 'state' => 'Adamawa', 'region' => 'North East Zone'],
            'mubi' => ['city' => 'Mubi', 'state' => 'Adamawa', 'region' => 'North East Zone'],
            'gombe_city' => ['city' => 'Gombe', 'state' => 'Gombe', 'region' => 'North East Zone'],
            'jalingo' => ['city' => 'Jalingo', 'state' => 'Taraba', 'region' => 'North East Zone'],
            'potiskum' => ['city' => 'Potiskum', 'state' => 'Yobe', 'region' => 'North East Zone'],
            'gashua' => ['city' => 'Gashua', 'state' => 'Yobe', 'region' => 'North East Zone'],
        ];
    }
}
