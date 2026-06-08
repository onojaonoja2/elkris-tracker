<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\StockistPendingDispatchesWidget;
use App\Filament\Widgets\StockistStatsWidget;
use App\Filament\Widgets\StockistStocksWidget;
use App\Filament\Widgets\StockistTrialOrdersWidget;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

class StockistDashboard extends BaseDashboard
{
    protected static string $routePath = '/stockist-dashboard';

    protected static ?string $slug = 'stockist-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'stockist';
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'stockist';
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function mount()
    {
        if (! auth()->check() || auth()->user()->role !== 'stockist') {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            StockistStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            StockistPendingDispatchesWidget::class,
            StockistStocksWidget::class,
            StockistTrialOrdersWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getRequestStockAction(),
            $this->getNewTrialOrderAction(),
        ];
    }

    private function getRequestStockAction(): Action
    {
        return Action::make('requestStock')
            ->label('Request Stock')
            ->icon('heroicon-o-shopping-cart')
            ->color('warning')
            ->form([
                Select::make('source_type')
                    ->label('Source Type')
                    ->options([
                        'warehouse' => 'From Warehouse',
                        'stockist' => 'From Stockist (Same State)',
                    ])
                    ->default('warehouse')
                    ->required()
                    ->live(),

                Select::make('from_warehouse_id')
                    ->label('From Warehouse')
                    ->options(fn () => Warehouse::orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->required()
                    ->visible(fn (callable $get) => $get('source_type') === 'warehouse')
                    ->live(),

                Select::make('from_stockist_id')
                    ->label('From Stockist')
                    ->options(function () {
                        $user = auth()->user();
                        $currentStockist = $user->stockist;

                        if (! $currentStockist) {
                            return [];
                        }

                        return Stockist::where('state_id', $currentStockist->state_id)
                            ->where('id', '!=', $currentStockist->id)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->required()
                    ->visible(fn (callable $get) => $get('source_type') === 'stockist')
                    ->live(),

                Repeater::make('items')
                    ->label('Stock Items')
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
                            ->options(function (callable $get) {
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
                    ->addActionLabel('Add Item')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->required(),

                Textarea::make('notes')
                    ->label('Request Notes'),
            ])
            ->action(function (array $data) {
                $user = auth()->user();
                $stockistId = $user->stockist_id;

                if ($data['source_type'] === 'warehouse') {
                    foreach ($data['items'] as $item) {
                        $inventory = Inventory::where('warehouse_id', $data['from_warehouse_id'])
                            ->where('product_type_id', $item['product_type_id'])
                            ->where('grammage', $item['grammage'])
                            ->first();

                        $available = $inventory?->quantity ?? 0;

                        if ($available < $item['quantity']) {
                            $productType = ProductType::find($item['product_type_id']);
                            $productName = $productType?->name ?? 'Unknown';

                            Notification::make()
                                ->danger()
                                ->title('Insufficient warehouse stock')
                                ->body("Only {$available} available of {$productName} ({$item['grammage']}g) in the selected warehouse, but {$item['quantity']} requested.")
                                ->send();

                            return;
                        }
                    }
                } else {
                    $fromStockist = Stockist::find($data['from_stockist_id']);
                    if (! $fromStockist) {
                        Notification::make()
                            ->danger()
                            ->title('Stockist not found')
                            ->send();

                        return;
                    }

                    foreach ($data['items'] as $item) {
                        $stock = StockistStock::where('stockist_id', $data['from_stockist_id'])
                            ->where('product_type_id', $item['product_type_id'])
                            ->where('grammage', $item['grammage'])
                            ->first();

                        $available = $stock?->quantity ?? 0;

                        if ($available < $item['quantity']) {
                            $productType = ProductType::find($item['product_type_id']);
                            $productName = $productType?->name ?? 'Unknown';

                            Notification::make()
                                ->danger()
                                ->title('Insufficient stockist stock')
                                ->body("Only {$available} available of {$productName} ({$item['grammage']}g) at {$fromStockist->name}, but {$item['quantity']} requested.")
                                ->send();

                            return;
                        }
                    }
                }

                if ($data['source_type'] === 'warehouse') {
                    $transfer = StockTransfer::create([
                        'from_warehouse_id' => $data['from_warehouse_id'],
                        'to_stockist_id' => $stockistId,
                        'requested_by' => $user->id,
                        'status' => 'requested',
                        'notes' => $data['notes'] ?? null,
                    ]);
                } else {
                    $transfer = StockTransfer::create([
                        'from_stockist_id' => $data['from_stockist_id'],
                        'to_stockist_id' => $stockistId,
                        'requested_by' => $user->id,
                        'status' => 'requested',
                        'notes' => $data['notes'] ?? null,
                    ]);
                }

                foreach ($data['items'] as $item) {
                    $transfer->items()->create($item);
                }

                Notification::make()
                    ->title('Stock request submitted successfully')
                    ->body('Your request has been sent for approval.')
                    ->success()
                    ->send();
            })
            ->modalHeading('Request Stock')
            ->modalButton('Submit Request');
    }

    private function getNewTrialOrderAction(): Action
    {
        return Action::make('newTrialOrder')
            ->label('New Trial Order')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->url(route('filament.admin.resources.trial-orders.create'));
    }
}
