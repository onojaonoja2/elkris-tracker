<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use App\Models\Lga;
use App\Models\Product;
use App\Models\State;
use App\Rules\UniquePhoneWithOwner;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                    ->required(fn (): bool => auth()->user()->hasRole('admin'))
                    ->visible(fn (): bool => auth()->user()->hasRole('admin')),

                // 2. REP SELECTION: Visible only to Admin
                MultiSelect::make('reps')
                    ->label('Assigned Reps')
                    ->relationship('reps', 'name', fn ($query) => $query->where('role', 'rep'))
                    ->searchable()
                    ->required(fn (): bool => auth()->user()->hasRole('admin'))
                    ->visible(fn (): bool => auth()->user()->hasRole('admin')),

                TextInput::make('customer_name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('phone_number')
                    ->tel()
                    ->required()
                    ->rule(fn (?Customer $record) => new UniquePhoneWithOwner(ignoreId: $record?->id))
                    ->maxLength(11)
                    ->regex('/^[0-9]{11}$/')
                    ->validationMessages([
                        'regex' => 'The phone number must be exactly 11 numeric digits without spaces or dashes.',
                    ]),

                TextInput::make('age')
                    ->numeric()
                    ->visible(fn () => ! auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])),

                Select::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ])
                    ->visible(fn () => ! auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])),

                Select::make('state_id')
                    ->label('State')
                    ->options(fn () => State::pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live(debounce: 300)
                    ->visible(fn () => ! auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market']))
                    ->default(fn () => self::getDefaultStateId())
                    ->afterStateUpdated(function (Set $set, $stateId) {
                        $set('lga_id', null);
                        $state = State::find($stateId);
                        $set('state', $state?->name);
                        $set('region', $state?->region?->name);
                    }),

                Hidden::make('state_id')
                    ->default(fn () => self::getDefaultStateId())
                    ->visible(fn () => auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])),

                Select::make('lga_id')
                    ->label('Local Government Area')
                    ->options(fn (Get $get) => $get('state_id')
                        ? Lga::where('state_id', $get('state_id'))->pluck('name', 'id')
                        : [])
                    ->searchable()
                    ->required()
                    ->live()
                    ->visible(fn () => ! auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market']))
                    ->default(fn () => self::getDefaultLgaId()),

                Hidden::make('lga_id')
                    ->default(fn () => self::getDefaultLgaId())
                    ->visible(fn () => auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])),

                TextInput::make('city')
                    ->required(),

                Hidden::make('state')
                    ->default(fn () => State::find(self::getDefaultStateId())?->name),

                Hidden::make('region')
                    ->default(fn () => State::find(self::getDefaultStateId())?->region?->name),

                Textarea::make('address')
                    ->required(fn () => auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market']))
                    ->columnSpanFull(),

                Hidden::make('agent_id'),

                Hidden::make('lead_id'),

                Hidden::make('customer_status')
                    ->default('customer'),

                Select::make('priority')
                    ->label('Customer Priority')
                    ->options([
                        'high' => 'High',
                        'medium' => 'Medium',
                        'low' => 'Low',
                    ])
                    ->default('medium')
                    ->required(),

                Select::make('diabetic_awareness')
                    ->options([
                        'yes' => 'Yes',
                        'no' => 'No',
                        'unknown' => 'Unknown',
                    ])
                    ->visible(fn () => ! auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])),

                DatePicker::make('call_date')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn () => ! auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])),

                TextInput::make('preffered_call_time')
                    ->visible(fn () => ! auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])),

                Textarea::make('feedback')
                    ->rows(3)
                    ->columnSpanFull()
                    ->visible(fn () => ! auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])),

                Textarea::make('remarks')
                    ->rows(3)
                    ->columnSpanFull()
                    ->visible(fn () => ! auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])),

                DatePicker::make('follow_up_date')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn () => ! auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])),

                Section::make('Lifetime Purchases')
                    ->columnSpanFull()
                    ->visible(fn () => ! auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market']))
                    ->schema([
                        Text::make(fn (?Customer $record): HtmlString => new HtmlString(
                            $record ? self::renderLifetimePurchasesTable($record) : 'No purchases recorded.'
                        )),
                    ]),

                // Products and order details are now handled via the OrdersRelationManager
            ]);
    }

    public static function renderLifetimePurchasesTable(Customer $record): string
    {
        $products = Product::whereHas('order', fn ($q) => $q
            ->where('customer_id', $record->id)
            ->where('status', 'delivered')
        )->get();

        $breakdown = [];
        foreach ($products as $product) {
            $key = $product->product_name.' - '.$product->grammage.'g';
            if (! isset($breakdown[$key])) {
                $breakdown[$key] = [
                    'product_name' => $product->product_name,
                    'grammage' => $product->grammage,
                    'total_qty' => 0,
                    'total_price' => 0,
                ];
            }
            $breakdown[$key]['total_qty'] += $product->quantity;
            $breakdown[$key]['total_price'] += $product->price * $product->quantity;
        }

        if (empty($breakdown)) {
            return '<p class="text-sm text-gray-500">No purchases recorded.</p>';
        }

        $html = '<table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">';
        $html .= '<thead><tr class="bg-gray-50 border-b border-gray-200">
            <th class="text-left py-2 px-3 font-medium text-gray-600">Product</th>
            <th class="text-left py-2 px-3 font-medium text-gray-600">Grammage</th>
            <th class="text-right py-2 px-3 font-medium text-gray-600">Qty</th>
            <th class="text-right py-2 px-3 font-medium text-gray-600">Unit Price</th>
            <th class="text-right py-2 px-3 font-medium text-gray-600">Subtotal</th>
        </tr></thead><tbody>';

        $grandTotal = 0;
        foreach ($breakdown as $data) {
            $unitPrice = $data['total_qty'] > 0 ? round($data['total_price'] / $data['total_qty'], 2) : 0;
            $subtotal = $data['total_price'];
            $grandTotal += $subtotal;

            $html .= '<tr class="border-b border-gray-100 hover:bg-gray-50">
                <td class="py-2 px-3">'.e($data['product_name']).'</td>
                <td class="py-2 px-3">'.$data['grammage'].'g</td>
                <td class="py-2 px-3 text-right">'.$data['total_qty'].'</td>
                <td class="py-2 px-3 text-right">₦'.number_format($unitPrice, 2).'</td>
                <td class="py-2 px-3 text-right">₦'.number_format($subtotal, 2).'</td>
            </tr>';
        }

        $html .= '</tbody><tfoot><tr class="bg-gray-50 font-semibold border-t-2 border-gray-300">
            <td colspan="4" class="py-2 px-3 text-right">Total</td>
            <td class="py-2 px-3 text-right">₦'.number_format($grandTotal, 2).'</td>
        </tr></tfoot></table>';

        return $html;
    }

    public static function getDefaultStateId(): ?int
    {
        $user = auth()->user();

        if (auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market']) && $user->lga) {
            return State::whereHas('lgas', fn ($q) => $q->where('id', $user->lga_id))->first()?->id;
        }

        return null;
    }

    public static function getDefaultLgaId(): ?int
    {
        $user = auth()->user();

        if (auth()->user()->hasAnyRole(['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])) {
            return $user->lga_id;
        }

        return null;
    }

    public static function getCityMapping(): array
    {
        return config('locations.cities', []);
    }
}
