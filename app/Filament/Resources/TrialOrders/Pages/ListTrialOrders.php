<?php

namespace App\Filament\Resources\TrialOrders\Pages;

use App\Filament\Resources\TrialOrders\TrialOrderResource;
use App\Filament\Resources\TrialOrders\Widgets\SupervisorTrialStatsWidget;
use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
use App\Models\TrialOrder;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\SelectFilter;
use Livewire\Attributes\Url;

class ListTrialOrders extends ListRecords
{
    #[Url]
    public ?string $state = null;

    protected static string $resource = TrialOrderResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            CreateAction::make()
                ->visible(fn () => auth()->user()->role === 'field_agent'),

            Action::make('filter_by_location')
                ->label('Filter by Location')
                ->icon('heroicon-o-funnel')
                ->form([
                    Select::make('state_filter')
                        ->label('Select State')
                        ->options(function () {
                            $stockists = Stockist::where('supervisor_id', auth()->id())
                                ->select('state')
                                ->distinct()
                                ->pluck('state')
                                ->toArray();

                            return array_combine($stockists, $stockists);
                        })
                        ->placeholder('All States'),
                ])
                ->action(function (array $data) {
                    $state = $data['state_filter'] ?? null;

                    $routeParams = $state ? ['state' => $state] : [];

                    return redirect()->to(route('filament.admin.resources.trial-orders.index', $routeParams));
                }),
        ];

        if (auth()->user()->role === 'supervisor') {
            $actions[] = Action::make('createForAgent')
                ->label('Create for Agent')
                ->icon('heroicon-o-user-plus')
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
                                ->required(),
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
                        ])
                        ->columns(4)
                        ->minItems(1)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->createTrialOrderForFieldAgent($data);
                })
                ->modalHeading('Create Trial Order for Field Agent')
                ->modalButton('Create');

            $actions[] = Action::make('createForStockist')
                ->label('Create for Stockist')
                ->icon('heroicon-o-building-storefront')
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
                ->modalButton('Create');

            $actions[] = Action::make('exportReport')
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
                ->modalButton('Export');
        }

        return $actions;
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

    protected static function getProductOptions(): array
    {
        return [
            'Elkris Oat Flour' => 'Elkris Oat Flour',
            'Elkris Plantain' => 'Elkris Plantain',
            'Elkris Poundo Yam' => 'Elkris Poundo Yam',
        ];
    }

    protected static function getGrammageOptions(?string $product): array
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
        $stockist = Stockist::where('city', $firstCity)->where('supervisor_id', auth()->id())->first();

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

    protected function getHeaderWidgets(): array
    {
        if (auth()->user()->role === 'supervisor') {
            return [
                SupervisorTrialStatsWidget::class,
            ];
        }

        return [];
    }

    protected function getTableFilters(): array
    {
        $stateFilter = $this->state;

        if (! $stateFilter) {
            return [];
        }

        $stateCities = Stockist::where('state', $stateFilter)
            ->pluck('city')
            ->toArray();

        return [
            SelectFilter::make('state')
                ->label('State')
                ->options(function () {
                    return Stockist::where('supervisor_id', auth()->id())
                        ->select('state')
                        ->distinct()
                        ->pluck('state', 'state')
                        ->toArray();
                })
                ->query(function ($query) use ($stateCities) {
                    if (empty($stateCities)) {
                        return;
                    }

                    $query->whereHas('agent', function ($q) use ($stateCities) {
                        foreach ($stateCities as $city) {
                            $q->orWhereJsonContains('assigned_cities', $city);
                        }
                    });
                }),
        ];
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
}
