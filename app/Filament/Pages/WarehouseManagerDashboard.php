<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\WarehouseDamagedReturnsWidget;
use App\Filament\Widgets\WarehouseManagerStatsWidget;
use App\Filament\Widgets\WarehouseManagerStockBreakdownWidget;
use App\Filament\Widgets\WarehouseRecentMovementsWidget;
use App\Filament\Widgets\WarehouseReturnApprovalsWidget;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\Setting;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\DB;

class WarehouseManagerDashboard extends BaseDashboard
{
    protected static string $routePath = '/warehouse-dashboard';

    protected static ?string $slug = 'warehouse-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('warehouse_manager');
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('warehouse_manager');
    }

    public function mount()
    {
        if (! auth()->check() || ! auth()->user()->hasRole('warehouse_manager')) {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            WarehouseManagerStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            WarehouseManagerStockBreakdownWidget::class,
            WarehouseDamagedReturnsWidget::class,
            WarehouseReturnApprovalsWidget::class,
            WarehouseRecentMovementsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receiveStock')
                ->label('Receive Stock')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    Select::make('warehouse_id')
                        ->label('Warehouse')
                        ->options(fn () => Warehouse::where('manager_id', auth()->id())->pluck('name', 'id'))
                        ->default(fn () => Warehouse::where('manager_id', auth()->id())->value('id'))
                        ->disabled()
                        ->dehydrated()
                        ->required(),
                    Select::make('source_type')
                        ->label('Stock Source')
                        ->options([
                            'warehouse' => 'From Warehouse',
                            'other' => 'Other',
                        ])
                        ->default('warehouse')
                        ->required()
                        ->live(),
                    Select::make('source_warehouse_id')
                        ->label('Source Warehouse')
                        ->options(fn () => Warehouse::pluck('name', 'id'))
                        ->searchable()
                        ->visible(fn (callable $get) => $get('source_type') === 'warehouse')
                        ->required(fn (callable $get) => $get('source_type') === 'warehouse'),
                    TextInput::make('source_name')
                        ->label('Source Name')
                        ->placeholder('e.g. Supplier ABC, Company XYZ')
                        ->visible(fn (callable $get) => $get('source_type') === 'other')
                        ->required(fn (callable $get) => $get('source_type') === 'other'),
                    FileUpload::make('dispatch_papers')
                        ->label('Dispatch Papers')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(10240)
                        ->directory('dispatch-papers')
                        ->multiple()
                        ->visible(fn (callable $get) => $get('source_type') === 'other'),
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

                                    return $pt
                                        ? collect($pt->available_grammages)
                                            ->map(fn ($g) => is_array($g) ? $g['grammage'] : $g)
                                            ->mapWithKeys(fn ($g) => [(string) $g => $g.'g'])
                                            ->toArray()
                                        : [];
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
                        ->label('Notes'),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    $sourceType = $data['source_type'] ?? 'warehouse';

                    // Store dispatch papers if uploaded
                    $papersPath = null;
                    if ($sourceType === 'other' && ! empty($data['dispatch_papers'])) {
                        $papers = $data['dispatch_papers'];
                        if (is_array($papers)) {
                            $papersPath = json_encode($papers);
                        } else {
                            $papersPath = $papers;
                        }
                    }

                    $transfer = StockTransfer::create([
                        'from_warehouse_id' => $sourceType === 'warehouse' ? ($data['source_warehouse_id'] ?? null) : null,
                        'to_warehouse_id' => $data['warehouse_id'],
                        'dispatched_by' => $user->id,
                        'requested_by' => $user->id,
                        'status' => 'requested',
                        'source_type' => $sourceType,
                        'source_name' => $sourceType === 'other' ? ($data['source_name'] ?? null) : null,
                        'dispatch_papers_path' => $papersPath,
                        'requires_approval' => true,
                        'notes' => $data['notes'] ?? null,
                    ]);

                    foreach ($data['items'] as $item) {
                        $transfer->items()->create([
                            'product_type_id' => $item['product_type_id'],
                            'grammage' => $item['grammage'],
                            'quantity' => $item['quantity'],
                        ]);
                    }

                    Notification::make()
                        ->title('Stock receive request submitted')
                        ->body('Awaiting accountant approval before stock is added to inventory.')
                        ->success()
                        ->send();
                }),

            Action::make('dispatchStock')
                ->label('Dispatch Stock')
                ->icon('heroicon-o-truck')
                ->color('warning')
                ->form([
                    Select::make('from_warehouse_id')
                        ->label('From Warehouse')
                        ->options(fn () => Warehouse::where('manager_id', auth()->id())->pluck('name', 'id'))
                        ->default(fn () => Warehouse::where('manager_id', auth()->id())->value('id'))
                        ->searchable()
                        ->required()
                        ->live(),
                    Select::make('to_type')
                        ->label('Dispatch To')
                        ->options([
                            'agent' => 'Agent',
                            'warehouse' => 'Another Warehouse',
                            'community_sales_representative' => 'Community Sales Representative',
                        ])
                        ->required()
                        ->live(),
                    Select::make('to_warehouse_id')
                        ->label('Destination Warehouse')
                        ->options(fn () => Warehouse::where('id', '!=', request()->input('from_warehouse_id'))->pluck('name', 'id'))
                        ->searchable()
                        ->visible(fn (callable $get) => $get('to_type') === 'warehouse')
                        ->required(fn (callable $get) => $get('to_type') === 'warehouse'),
                    Select::make('to_agent_id')
                        ->label('Select CSR')
                        ->options(fn () => User::where('role', 'community_sales_representative')
                            ->where('is_active', true)
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->visible(fn (callable $get) => $get('to_type') === 'community_sales_representative')
                        ->required(fn (callable $get) => $get('to_type') === 'community_sales_representative'),
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

                                    return $pt
                                        ? collect($pt->available_grammages)
                                            ->map(fn ($g) => is_array($g) ? $g['grammage'] : $g)
                                            ->mapWithKeys(fn ($g) => [(string) $g => $g.'g'])
                                            ->toArray()
                                        : [];
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
                        ->label('Dispatch Notes'),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();

                    try {
                        DB::transaction(function () use ($data, $user) {
                            $insufficientItems = [];
                            $inventoryToDecrement = [];

                            foreach ($data['items'] as $item) {
                                $inv = Inventory::where([
                                    'warehouse_id' => $data['from_warehouse_id'],
                                    'product_type_id' => $item['product_type_id'],
                                    'grammage' => $item['grammage'],
                                ])->lockForUpdate()->first();

                                $available = $inv?->quantity ?? 0;
                                if ($available < $item['quantity']) {
                                    $pt = ProductType::find($item['product_type_id']);
                                    $productName = $pt?->name ?? 'Unknown';
                                    $insufficientItems[] = "{$productName} {$item['grammage']}g (requested: {$item['quantity']}, available: {$available})";
                                } else {
                                    $inventoryToDecrement[] = [
                                        'inventory' => $inv,
                                        'quantity' => $item['quantity'],
                                    ];
                                }
                            }

                            if (! empty($insufficientItems)) {
                                throw new \Exception('Insufficient stock: '.implode(', ', $insufficientItems));
                            }

                            $transferData = [
                                'from_warehouse_id' => $data['from_warehouse_id'],
                                'dispatched_by' => $user->id,
                                'status' => 'dispatched',
                                'notes' => $data['notes'] ?? null,
                            ];

                            if (($data['to_type'] ?? null) === 'warehouse') {
                                $transferData['to_warehouse_id'] = $data['to_warehouse_id'];
                            }

                            if (($data['to_type'] ?? null) === 'community_sales_representative') {
                                $transferData['to_agent_id'] = $data['to_agent_id'];
                            }

                            $transfer = StockTransfer::create($transferData);

                            foreach ($data['items'] as $item) {
                                $transfer->items()->create($item);
                            }

                            foreach ($inventoryToDecrement as $entry) {
                                $entry['inventory']->decrement('quantity', $entry['quantity']);
                            }

                            $pdf = Pdf::loadView('pdf.dispatch-note', ['transfer' => $transfer]);
                            $pdfPath = storage_path('app/public/dispatch-'.$transfer->id.'.pdf');
                            $pdf->save($pdfPath);
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
                }),
        ];

        if (Setting::getValue('stock_at_hand_enabled', '0') === '1') {
            $actions[] = Action::make('submitStockCount')
                ->label('Submit Stock Count')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('info')
                ->form([
                    Select::make('warehouse_id')
                        ->label('Warehouse')
                        ->options(fn () => Warehouse::where('manager_id', auth()->id())->pluck('name', 'id'))
                        ->default(fn () => Warehouse::where('manager_id', auth()->id())->value('id'))
                        ->disabled()
                        ->dehydrated()
                        ->required(),
                    Repeater::make('items')
                        ->label('Physical Stock Count')
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
                                ->label('Quantity on Hand')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->required(),
                        ])
                        ->addActionLabel('Add Item')
                        ->defaultItems(1)
                        ->minItems(1)
                        ->required(),
                    Textarea::make('notes')
                        ->label('Notes'),
                ])
                ->action(function (array $data) {
                    $stockCount = StockCount::create([
                        'user_id' => auth()->id(),
                        'warehouse_id' => $data['warehouse_id'],
                        'status' => 'pending',
                        'notes' => $data['notes'] ?? null,
                    ]);

                    foreach ($data['items'] as $item) {
                        $pt = ProductType::find($item['product_type_id']);
                        $stockCount->items()->create([
                            'product_type_id' => $item['product_type_id'],
                            'product_name' => $pt?->name ?? 'Unknown Product',
                            'grammage' => $item['grammage'],
                            'quantity' => $item['quantity'],
                        ]);
                    }

                    Notification::make()
                        ->title('Stock count submitted')
                        ->body('Your physical stock count is pending accountant approval.')
                        ->success()
                        ->send();
                })
                ->modalHeading('Submit Physical Stock Count')
                ->modalButton('Submit Count');
        }

        return $actions;
    }
}
