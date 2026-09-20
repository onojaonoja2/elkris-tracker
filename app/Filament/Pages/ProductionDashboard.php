<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ProductionActivityWidget;
use App\Filament\Widgets\ProductionOutgoingTransfersWidget;
use App\Filament\Widgets\ProductionRawMaterialsWidget;
use App\Filament\Widgets\ProductionRunsWidget;
use App\Filament\Widgets\ProductionStoreStockWidget;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\DB;

class ProductionDashboard extends BaseDashboard
{
    protected static string $routePath = '/production-dashboard';

    protected static ?string $slug = 'production-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('production_management');
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('production_management');
    }

    public function mount()
    {
        if (! auth()->check() || ! auth()->user()->hasRole('production_management')) {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            ProductionActivityWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            ProductionRunsWidget::class,
            ProductionRawMaterialsWidget::class,
            ProductionStoreStockWidget::class,
            ProductionOutgoingTransfersWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dispatchStock')
                ->label('Dispatch Stock')
                ->icon('heroicon-o-truck')
                ->color('warning')
                ->modalHeading('Dispatch From Production Store')
                ->modalDescription(fn (): string => ($store = Warehouse::productionStore())
                    ? "Dispatch finished goods held at {$store->name}."
                    : 'No production warehouse has been designated yet.')
                ->form([
                    Select::make('to_type')
                        ->label('Dispatch To')
                        ->options([
                            'warehouse' => 'Another Warehouse',
                            'agent' => 'Agent',
                            'community_sales_representative' => 'Community Sales Representative',
                        ])
                        ->required()
                        ->live(),
                    Select::make('to_warehouse_id')
                        ->label('Destination Warehouse')
                        ->options(function (): array {
                            $storeId = Warehouse::productionStore()?->id;

                            return Warehouse::where('is_active', true)
                                ->when($storeId, fn ($query) => $query->where('id', '!=', $storeId))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->visible(fn (callable $get): bool => $get('to_type') === 'warehouse')
                        ->required(fn (callable $get): bool => $get('to_type') === 'warehouse'),
                    Select::make('to_agent_id')
                        ->label(fn (callable $get): string => $get('to_type') === 'community_sales_representative' ? 'Select CSR' : 'Select Agent')
                        ->options(function (callable $get): array {
                            $roles = $get('to_type') === 'community_sales_representative'
                                ? ['community_sales_representative']
                                : ['open_market', 'retail_market', 'sales'];

                            return User::whereIn('role', $roles)
                                ->active()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->visible(fn (callable $get): bool => in_array($get('to_type'), ['agent', 'community_sales_representative']))
                        ->required(fn (callable $get): bool => in_array($get('to_type'), ['agent', 'community_sales_representative'])),
                    Repeater::make('items')
                        ->label('Stock Items')
                        ->schema([
                            Select::make('product_type_id')
                                ->label('Product')
                                ->options(function (): array {
                                    $storeId = Warehouse::productionStore()?->id;

                                    if (! $storeId) {
                                        return [];
                                    }

                                    $heldIds = Inventory::where('warehouse_id', $storeId)
                                        ->where('quantity', '>', 0)
                                        ->pluck('product_type_id')
                                        ->unique();

                                    return ProductType::whereIn('id', $heldIds)
                                        ->where('is_active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn ($set) => $set('grammage', null)),
                            Select::make('grammage')
                                ->label('Weight (g)')
                                ->options(function (callable $get): array {
                                    $storeId = Warehouse::productionStore()?->id;
                                    $productTypeId = $get('product_type_id');

                                    if (! $storeId || ! $productTypeId) {
                                        return [];
                                    }

                                    return Inventory::where('warehouse_id', $storeId)
                                        ->where('product_type_id', $productTypeId)
                                        ->where('quantity', '>', 0)
                                        ->orderBy('grammage')
                                        ->pluck('grammage', 'grammage')
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
                                ->required()
                                ->helperText(fn (callable $get): ?string => $this->storeAvailabilityHint($get)),
                        ])
                        ->addActionLabel('Add Item')
                        ->defaultItems(1)
                        ->minItems(1)
                        ->required(),
                    Textarea::make('notes')
                        ->label('Dispatch Notes'),
                ])
                ->action(function (array $data): void {
                    $user = auth()->user();

                    $store = Warehouse::productionStore();

                    if (! $store) {
                        Notification::make()
                            ->danger()
                            ->title('Dispatch failed')
                            ->body('No production warehouse has been designated yet.')
                            ->send();

                        return;
                    }

                    try {
                        DB::transaction(function () use ($data, $user, $store) {
                            $insufficientItems = [];
                            $inventoryToDecrement = [];

                            foreach ($data['items'] as $item) {
                                $inventory = Inventory::where([
                                    'warehouse_id' => $store->id,
                                    'product_type_id' => $item['product_type_id'],
                                    'grammage' => $item['grammage'],
                                ])->lockForUpdate()->first();

                                $available = $inventory?->quantity ?? 0;

                                if ($available < $item['quantity']) {
                                    $productName = $inventory?->productType?->name
                                        ?? ProductType::find($item['product_type_id'])?->name
                                        ?? 'Unknown';
                                    $insufficientItems[] = "{$productName} {$item['grammage']}g (requested: {$item['quantity']}, available: {$available})";
                                } else {
                                    $inventoryToDecrement[] = [
                                        'inventory' => $inventory,
                                        'product' => $inventory->productType?->name ?? 'Unknown',
                                        'product_type_id' => $item['product_type_id'],
                                        'grammage' => $item['grammage'],
                                        'quantity' => $item['quantity'],
                                    ];
                                }
                            }

                            if (! empty($insufficientItems)) {
                                throw new \Exception('Insufficient stock: '.implode(', ', $insufficientItems));
                            }

                            $transferData = [
                                'from_warehouse_id' => $store->id,
                                'dispatched_by' => $user->id,
                                'requested_by' => $user->id,
                                'source_type' => 'production_dispatch',
                                'status' => 'dispatched',
                                'notes' => $data['notes'] ?? null,
                            ];

                            if (($data['to_type'] ?? null) === 'warehouse') {
                                $transferData['to_warehouse_id'] = $data['to_warehouse_id'];
                                $destinationLabel = 'Warehouse #'.$data['to_warehouse_id'];
                            } else {
                                $transferData['to_agent_id'] = $data['to_agent_id'];
                                $destinationLabel = 'Agent #'.$data['to_agent_id'];
                            }

                            $transfer = StockTransfer::create($transferData);

                            foreach ($data['items'] as $item) {
                                $transfer->items()->create($item);
                            }

                            foreach ($inventoryToDecrement as $entry) {
                                $entry['inventory']->decrement('quantity', $entry['quantity']);

                                StockTransaction::create([
                                    'type' => 'disbursed',
                                    'transaction_date' => now()->toDateString(),
                                    'product_type_id' => $entry['product_type_id'],
                                    'product_name' => $entry['product'],
                                    'grammage' => $entry['grammage'],
                                    'quantity' => $entry['quantity'],
                                    'disbursed_to' => $destinationLabel.' (Production Dispatch #'.$transfer->id.')',
                                    'user_id' => $user->id,
                                    'warehouse_id' => $store->id,
                                ]);
                            }
                        });
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Dispatch failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()->title('Stock dispatched successfully')->success()->send();

                    $this->dispatch('refresh-dashboard');
                }),
        ];
    }

    private function storeAvailabilityHint(callable $get): ?string
    {
        $storeId = Warehouse::productionStore()?->id;
        $productTypeId = $get('product_type_id');
        $grammage = $get('grammage');

        if (! $storeId || ! $productTypeId || ! $grammage) {
            return null;
        }

        $available = Inventory::where([
            'warehouse_id' => $storeId,
            'product_type_id' => $productTypeId,
            'grammage' => $grammage,
        ])->value('quantity') ?? 0;

        return "Available in production store: {$available}";
    }
}
