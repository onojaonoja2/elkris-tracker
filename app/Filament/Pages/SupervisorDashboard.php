<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Stockists\StockistResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
use App\Models\TrialOrder;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SupervisorDashboard extends BaseDashboard
{
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->role === 'supervisor';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SupervisorStatsWidget::class,
            SupervisorStockWidget::class,
        ];
    }

    public function mount()
    {
        if (auth()->user()->role !== 'supervisor') {
            return redirect()->to(CustomerResource::getUrl('index'));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addStockist')
                ->label('Add Stockist')
                ->icon('heroicon-o-plus')
                ->button()
                ->url(StockistResource::getUrl('create')),

            Action::make('addFieldAgent')
                ->label('Add Field Agent')
                ->icon('heroicon-o-user-plus')
                ->button()
                ->url(UserResource::getUrl('create')),

            Action::make('receiveStock')
                ->label('Receive Stock')
                ->icon('heroicon-o-arrow-down-on-square')
                ->button()
                ->form([
                    Select::make('stockist_id')
                        ->label('Select Stockist')
                        ->options(fn () => Stockist::where('supervisor_id', auth()->id())
                            ->pluck('name', 'id'))
                        ->required(),
                    Repeater::make('products')
                        ->label('Products')
                        ->schema([
                            Select::make('product_name')
                                ->label('Product')
                                ->options(self::getProductOptions())
                                ->required()
                                ->live(),
                            Select::make('grammage')
                                ->label('Grammage')
                                ->options(fn (Get $get) => self::getGrammageOptions($get('product_name') ?? $get('products.product_name')))
                                ->required(),
                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                            TextInput::make('unit_price')
                                ->label('Unit Price')
                                ->numeric()
                                ->prefix('₦')
                                ->required(),
                        ])
                        ->columns(4)
                        ->minItems(1)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $stockist = Stockist::find($data['stockist_id']);
                    if ($stockist) {
                        $totalValue = 0;

                        foreach ($data['products'] as $product) {
                            $lineTotal = $product['quantity'] * $product['unit_price'];
                            $totalValue += $lineTotal;

                            StockistStock::updateOrCreate([
                                'stockist_id' => $stockist->id,
                                'product_name' => $product['product_name'],
                                'grammage' => $product['grammage'],
                            ], [
                                'quantity' => DB::raw("quantity + {$product['quantity']}"),
                                'unit_price' => $product['unit_price'],
                            ]);

                            StockistTransaction::create([
                                'stockist_id' => $stockist->id,
                                'user_id' => auth()->id(),
                                'type' => 'received',
                                'amount' => $lineTotal,
                                'description' => "Received {$product['quantity']}x {$product['product_name']} ({$product['grammage']}g)",
                                'transaction_date' => now()->toDateString(),
                            ]);
                        }

                        $stockist->increment('stock_balance', $totalValue);
                    }
                })
                ->modalHeading('Receive Stock')
                ->modalButton('Receive'),

            Action::make('exportReport')
                ->label('Export Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->button()
                ->form([
                    DatePicker::make('start_date')
                        ->label('Start Date')
                        ->required(),
                    DatePicker::make('end_date')
                        ->label('End Date')
                        ->required(),
                ])
                ->action(function (array $data) {
                    return $this->exportReport($data['start_date'], $data['end_date']);
                })
                ->modalHeading('Export Activity Report')
                ->modalButton('Export'),

            Action::make('createTrialOrder')
                ->label('Create Trial Order')
                ->icon('heroicon-o-clipboard-document')
                ->button()
                ->form([
                    Select::make('field_agent_id')
                        ->label('Select Field Agent')
                        ->options(fn () => $this->getFieldAgentOptions())
                        ->required()
                        ->searchable(),
                    Repeater::make('products')
                        ->label('Products')
                        ->schema([
                            Select::make('product_name')
                                ->label('Product')
                                ->options(self::getProductOptions())
                                ->required()
                                ->live(),
                            Select::make('grammage')
                                ->label('Grammage')
                                ->options(fn (Get $get) => self::getGrammageOptions($get('product_name') ?? $get('products.product_name')))
                                ->required(),
                            TextInput::make('quantity')
                                ->label('Qty')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                            TextInput::make('price')
                                ->label('Unit Price')
                                ->numeric()
                                ->prefix('₦')
                                ->required(),
                            TextInput::make('line_total')
                                ->label('Line Total')
                                ->numeric()
                                ->prefix('₦')
                                ->readOnly()
                                ->dehydrated(false),
                        ])
                        ->columns(5)
                        ->minItems(1)
                        ->required(),
                    TextInput::make('total_value')
                        ->label('Total Value')
                        ->numeric()
                        ->prefix('₦')
                        ->readOnly(),
                ])
                ->action(function (array $data) {
                    $this->createTrialOrderForFieldAgent($data);
                })
                ->modalHeading('Create Trial Order for Field Agent')
                ->modalButton('Create'),

            Action::make('createStockistTrialOrder')
                ->label('Create Stockist Trial Order')
                ->icon('heroicon-o-building-storefront')
                ->button()
                ->form([
                    Select::make('stockist_id')
                        ->label('Select Stockist')
                        ->options(fn () => Stockist::where('supervisor_id', auth()->id())
                            ->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    Repeater::make('stockist_products')
                        ->label('Products')
                        ->schema([
                            Select::make('product_name')
                                ->label('Product')
                                ->options(self::getProductOptions())
                                ->required(),
                            Select::make('grammage')
                                ->label('Grammage')
                                ->options(fn (Get $get) => self::getGrammageOptions($get('product_name') ?? $get('stockist_products.product_name')))
                                ->required(),
                            TextInput::make('quantity')
                                ->label('Qty')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                            TextInput::make('price')
                                ->label('Unit Price')
                                ->numeric()
                                ->prefix('₦')
                                ->required(),
                        ])
                        ->columns(4)
                        ->minItems(1)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->createTrialOrderForStockist($data);
                })
                ->modalHeading('Create Stockist Trial Order')
                ->modalButton('Create'),
        ];
    }

    protected function getFieldAgentOptions(): array
    {
        $user = auth()->user();
        $stockists = Stockist::where('supervisor_id', $user->id)->get();
        $stockistCities = $stockists->pluck('city')->toArray();

        return User::where('role', 'field_agent')
            ->where(function ($query) use ($stockistCities) {
                foreach ($stockistCities as $city) {
                    $query->orWhereJsonContains('assigned_cities', $city);
                }
            })
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function recalculateFormTotal(Set $set, Get $get): void
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

    protected static function updateLineTotal(Set $set, Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 1);
        $price = (float) ($get('price') ?? 0);
        $set('line_total', $quantity * $price);
    }

    protected function createTrialOrderForFieldAgent(array $data): void
    {
        $agent = User::find($data['field_agent_id']);
        if (! $agent) {
            return;
        }

        $products = $data['products'] ?? [];
        if (empty($products)) {
            return;
        }

        $totalValue = 0;
        foreach ($products as $product) {
            $qty = (float) ($product['quantity'] ?? 1);
            $price = (float) ($product['price'] ?? 0);
            $totalValue += $qty * $price;
        }

        if ($totalValue <= 0) {
            return;
        }

        $trialOrder = TrialOrder::create([
            'agent_id' => $agent->id,
            'products' => $products,
            'total_value' => $totalValue,
            'status' => 'approved',
            'approved_by' => auth()->id(),
        ]);

        $agent->increment('stock_balance', $totalValue);

        $stockistCities = is_array($agent->assigned_cities) ? $agent->assigned_cities : [];
        $firstCity = $stockistCities[0] ?? null;
        $stockist = Stockist::where('city', $firstCity)->first();

        if ($stockist) {
            $totalDeducted = 0;
            foreach ($products as $product) {
                $productName = $product['product_name'] ?? null;
                $grammage = $product['grammage'] ?? null;
                $quantity = $product['quantity'] ?? 0;
                $price = $product['price'] ?? 0;
                $lineTotal = $quantity * $price;

                if ($productName && $grammage && $quantity > 0) {
                    $stockistStock = StockistStock::where('stockist_id', $stockist->id)
                        ->where('product_name', $productName)
                        ->where('grammage', $grammage)
                        ->first();

                    if ($stockistStock && $stockistStock->quantity >= $quantity) {
                        $stockistStock->decrement('quantity', $quantity);
                        $totalDeducted += $lineTotal;

                        StockistTransaction::create([
                            'stockist_id' => $stockist->id,
                            'user_id' => auth()->id(),
                            'field_agent_id' => $agent->id,
                            'trial_order_id' => $trialOrder->id,
                            'type' => 'deducted',
                            'amount' => $lineTotal,
                            'description' => "Deducted {$quantity}x {$productName} ({$grammage}g) for trial order",
                            'transaction_date' => now()->toDateString(),
                        ]);
                    }
                }
            }

            if ($totalDeducted > 0) {
                $stockist->decrement('stock_balance', $totalDeducted);
            }
        }
    }

    protected function createTrialOrderForStockist(array $data): void
    {
        $stockist = Stockist::find($data['stockist_id']);
        if (! $stockist) {
            return;
        }

        $products = $data['stockist_products'] ?? [];
        if (empty($products)) {
            return;
        }

        $totalValue = 0;
        foreach ($products as $product) {
            $qty = (float) ($product['quantity'] ?? 1);
            $price = (float) ($product['price'] ?? 0);
            $totalValue += $qty * $price;
        }

        if ($totalValue <= 0) {
            return;
        }

        $trialOrder = TrialOrder::create([
            'stockist_id' => $stockist->id,
            'products' => $products,
            'total_value' => $totalValue,
            'status' => 'approved',
            'approved_by' => auth()->id(),
        ]);

        $totalDeducted = 0;
        foreach ($products as $product) {
            $productName = $product['product_name'] ?? null;
            $grammage = $product['grammage'] ?? null;
            $quantity = $product['quantity'] ?? 0;
            $price = $product['price'] ?? 0;
            $lineTotal = $quantity * $price;

            if ($productName && $grammage && $quantity > 0) {
                $stockistStock = StockistStock::where('stockist_id', $stockist->id)
                    ->where('product_name', $productName)
                    ->where('grammage', $grammage)
                    ->first();

                if ($stockistStock && $stockistStock->quantity >= $quantity) {
                    $stockistStock->decrement('quantity', $quantity);
                    $totalDeducted += $lineTotal;

                    StockistTransaction::create([
                        'stockist_id' => $stockist->id,
                        'user_id' => auth()->id(),
                        'trial_order_id' => $trialOrder->id,
                        'type' => 'deducted',
                        'amount' => $lineTotal,
                        'description' => "Stockist trial: {$quantity}x {$productName} ({$grammage}g)",
                        'transaction_date' => now()->toDateString(),
                    ]);
                }
            }
        }

        if ($totalDeducted > 0) {
            $stockist->decrement('stock_balance', $totalDeducted);
        }
    }

    protected function exportReport(string $startDate, string $endDate)
    {
        $transactions = StockistTransaction::whereHas('stockist', fn ($q) => $q->where('supervisor_id', auth()->id()))
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'asc')
            ->get();

        $filename = 'supervisor_report_'.date('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Date', 'Stockist', 'Type', 'Amount', 'Field Agent', 'Description']);

            foreach ($transactions as $t) {
                fputcsv($handle, [
                    $t->transaction_date->format('d/m/Y'),
                    $t->stockist->name ?? 'N/A',
                    $t->type,
                    $t->amount,
                    $t->fieldAgent->name ?? 'N/A',
                    $t->description,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public static function getProductOptions(): array
    {
        return [
            'Elkris Oat Flour' => 'Elkris Oat Flour',
            'Elkris Plantain' => 'Elkris Plantain',
            'Elkris Poundo Yam' => 'Elkris Poundo Yam',
        ];
    }

    public static function getGrammageOptions(?string $product): array
    {
        if (! $product) {
            return [];
        }

        return match ($product) {
            'Elkris Oat Flour' => [
                '5000' => '5000g',
                '1300' => '1300g',
                '650' => '650g',
            ],
            'Elkris Plantain' => [
                '1800' => '1800g',
                '900' => '900g',
            ],
            'Elkris Poundo Yam' => [
                '1800' => '1800g',
            ],
            default => [],
        };
    }
}

class SupervisorStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $stockists = Stockist::where('supervisor_id', $user->id)->get();
        $stockistIds = $stockists->pluck('id');

        $totalStockValue = $stockists->sum('stock_balance');
        $stockistCount = $stockists->count();

        $stockistCities = $stockists->pluck('city')->toArray();
        $fieldAgentCount = User::where('role', 'field_agent')
            ->where(function ($query) use ($stockistCities) {
                foreach ($stockistCities as $city) {
                    $query->orWhereJsonContains('assigned_cities', $city);
                }
            })
            ->count();

        $faIds = User::where('role', 'field_agent')
            ->where(function ($query) use ($stockistCities) {
                foreach ($stockistCities as $city) {
                    $query->orWhereJsonContains('assigned_cities', $city);
                }
            })
            ->pluck('id')
            ->toArray();

        $pendingOrdersCount = TrialOrder::where('status', 'pending')
            ->whereIn('agent_id', $faIds)
            ->count();

        return [
            Stat::make('Total Stock Value', '₦'.number_format($totalStockValue, 0))
                ->description('All stockists combined')
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),
            Stat::make('Stockists', $stockistCount)
                ->description('Registered stockists')
                ->icon('heroicon-o-building-storefront')
                ->color('info'),
            Stat::make('Field Agents', $fieldAgentCount)
                ->description('Active field agents')
                ->icon('heroicon-o-users')
                ->color('warning'),
            Stat::make('Pending Trial Orders', $pendingOrdersCount)
                ->description('Awaiting approval')
                ->icon('heroicon-o-clock')
                ->color('danger'),
        ];
    }
}
