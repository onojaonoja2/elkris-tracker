<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasDashboardBreakdownModals;
use App\Filament\Pages\Concerns\HasDashboardDateFilter;
use App\Filament\Widgets\WarehouseCsrStockWidget;
use App\Filament\Widgets\WarehouseDamagedReturnsWidget;
use App\Filament\Widgets\WarehouseDirectSalesWidget;
use App\Filament\Widgets\WarehouseDispatchRequestsWidget;
use App\Filament\Widgets\WarehouseManagerStatsWidget;
use App\Filament\Widgets\WarehouseManagerStockBreakdownWidget;
use App\Filament\Widgets\WarehouseOutgoingDispatchesWidget;
use App\Filament\Widgets\WarehouseRecentMovementsWidget;
use App\Filament\Widgets\WarehouseRepacksWidget;
use App\Filament\Widgets\WarehouseReturnApprovalsWidget;
use App\Filament\Widgets\WarehouseStocksWidget;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\RawMaterial;
use App\Models\Setting;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RawMaterialReceiptService;
use App\Services\RawMaterialTransferService;
use App\Services\RepackService;
use App\Services\SalesRecordService;
use App\Support\DashboardDateScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseManagerDashboard extends BaseDashboard
{
    use HasDashboardBreakdownModals, HasDashboardDateFilter;

    protected static string $routePath = '/warehouse-dashboard';

    protected static ?string $slug = 'warehouse-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && (auth()->user()?->canManageWarehouses() ?? false);
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && (auth()->user()?->canManageWarehouses() ?? false);
    }

    public function mount()
    {
        if (! auth()->check() || ! (auth()->user()?->canManageWarehouses() ?? false)) {
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
            WarehouseDispatchRequestsWidget::class,
            WarehouseOutgoingDispatchesWidget::class,
            WarehouseDirectSalesWidget::class,
            WarehouseManagerStockBreakdownWidget::class,
            WarehouseCsrStockWidget::class,
            WarehouseStocksWidget::class,
            WarehouseDamagedReturnsWidget::class,
            WarehouseReturnApprovalsWidget::class,
            WarehouseRepacksWidget::class,
            WarehouseRecentMovementsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            $this->getDateFilterAction(),
            $this->getClearDateFilterAction(),
            Action::make('exportReport')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => $this->exportReport()),
            Action::make('csrSalesValue')
                ->label('CSR Sales Value')
                ->icon('heroicon-o-currency-dollar')
                ->color('info')
                ->modalHeading('CSR Sales Value')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(function (): View {
                    return view('filament.csr-sales-value-modal');
                })
                ->visible(fn (): bool => auth()->user()?->hasRole('warehouse_manager') ?? false),
            Action::make('damagedStock')
                ->label('Damaged Stock')
                ->icon('heroicon-o-archive-box-x-mark')
                ->color('danger')
                ->url(WarehouseDamagedStock::getUrl()),
            $this->getStockMovementBreakdownAction(),
        ];

        $actions[] = Action::make('receiveStock')
            ->label('Receive Stock')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->form([
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): array => $this->managedWarehouseOptions())
                    ->default(fn (): ?int => $this->currentManagedWarehouseId())
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
                    ->options(fn (callable $get): array => self::activeWarehousesExcluding((int) $get('warehouse_id')))
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
                            ->label('Weight')
                            ->options(function (callable $get) {
                                $ptId = $get('product_type_id');
                                if (! $ptId) {
                                    return [];
                                }
                                $pt = ProductType::find($ptId);

                                return $pt
                                    ? collect($pt->available_grammages)
                                        ->map(fn ($g) => is_array($g) ? $g['grammage'] : $g)
                                        ->mapWithKeys(fn ($g) => [(string) $g => ProductType::formatGrammage($pt, (int) $g)])
                                        ->toArray()
                                    : [];
                            })
                            ->required()
                            ->live(),
                        TextInput::make('cartons')
                            ->label(fn (callable $get): string => self::packagingPluralLabel($get))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(fn ($set, callable $get) => self::recalculateReceiveItemTotal($set, $get)),
                        TextInput::make('pieces')
                            ->label('Units')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(fn ($set, callable $get) => self::recalculateReceiveItemTotal($set, $get)),
                        TextInput::make('quantity')
                            ->label('Total Pieces')
                            ->numeric()
                            ->integer()
                            ->readOnly()
                            ->default(0)
                            ->minValue(1),
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
                        'quantity' => self::totalPieces($item),
                    ]);
                }

                Notification::make()
                    ->title('Stock receive request submitted')
                    ->body('Awaiting accountant approval before stock is added to inventory.')
                    ->success()
                    ->send();

                $this->dispatch('refresh-dashboard');
            });

        $actions[] = Action::make('dispatchStock')
            ->label('Dispatch Stock')
            ->icon('heroicon-o-truck')
            ->color('warning')
            ->form([
                Select::make('from_warehouse_id')
                    ->label('From Warehouse')
                    ->options(fn (): array => $this->managedWarehouseOptions())
                    ->default(fn (): ?int => $this->currentManagedWarehouseId())
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
                    ->options(fn (callable $get): array => self::activeWarehousesExcluding((int) $get('from_warehouse_id')))
                    ->searchable()
                    ->visible(fn (callable $get) => $get('to_type') === 'warehouse')
                    ->required(fn (callable $get) => $get('to_type') === 'warehouse'),
                Select::make('to_agent_id')
                    ->label(fn (callable $get): string => $get('to_type') === 'community_sales_representative' ? 'Select CSR' : 'Select Agent')
                    ->options(function (callable $get) {
                        $roles = $get('to_type') === 'community_sales_representative'
                            ? ['community_sales_representative']
                            : ['open_market', 'retail_market', 'sales'];

                        return User::whereIn('role', $roles)
                            ->where('is_active', true)
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->visible(fn (callable $get) => in_array($get('to_type'), ['agent', 'community_sales_representative']))
                    ->required(fn (callable $get) => in_array($get('to_type'), ['agent', 'community_sales_representative'])),
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
                            ->label('Weight')
                            ->options(function (callable $get) {
                                $ptId = $get('product_type_id');
                                if (! $ptId) {
                                    return [];
                                }
                                $pt = ProductType::find($ptId);

                                return $pt
                                    ? collect($pt->available_grammages)
                                        ->map(fn ($g) => is_array($g) ? $g['grammage'] : $g)
                                        ->mapWithKeys(fn ($g) => [(string) $g => ProductType::formatGrammage($pt, (int) $g)])
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

                if (($data['to_type'] ?? null) === 'warehouse'
                    && (int) ($data['to_warehouse_id'] ?? 0) === (int) $data['from_warehouse_id']) {
                    Notification::make()
                        ->title('Dispatch failed')
                        ->body('Source and destination warehouses must differ.')
                        ->danger()
                        ->send();

                    return;
                }

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
                                $insufficientItems[] = "{$productName} ".ProductType::formatGrammage($pt, (int) $item['grammage'])." (requested: {$item['quantity']}, available: {$available})";
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

                        if (in_array($data['to_type'] ?? null, ['agent', 'community_sales_representative'])) {
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

                $this->dispatch('refresh-dashboard');
            });

        $actions[] = Action::make('recordDirectSale')
            ->label('Record Sale')
            ->icon('heroicon-o-currency-dollar')
            ->color('success')
            ->form([
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): array => $this->managedWarehouseOptions())
                    ->default(fn (): ?int => $this->currentManagedWarehouseId())
                    ->searchable()
                    ->required()
                    ->live(),
                Repeater::make('items')
                    ->label('Sale Items')
                    ->schema([
                        Select::make('product_type_id')
                            ->label('Product')
                            ->options(fn () => ProductType::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('grammage', null)),
                        Select::make('grammage')
                            ->label('Weight')
                            ->options(function (callable $get) {
                                $ptId = $get('product_type_id');
                                if (! $ptId) {
                                    return [];
                                }
                                $pt = ProductType::find($ptId);

                                return $pt
                                    ? collect($pt->available_grammages)
                                        ->map(fn ($g) => is_array($g) ? $g['grammage'] : $g)
                                        ->mapWithKeys(fn ($g) => [(string) $g => ProductType::formatGrammage($pt, (int) $g)])
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
                        TextInput::make('price')
                            ->label('Unit Price (₦)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->addActionLabel('Add Item')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->required(),
                TextInput::make('customer_name')
                    ->label('Customer Name (optional)'),
                TextInput::make('customer_phone')
                    ->label('Customer Phone (optional)')
                    ->tel(),
                FileUpload::make('receipt_path')
                    ->label('Receipt / Payment Proof (optional)')
                    ->image()
                    ->maxSize(2048)
                    ->disk('s3')
                    ->directory('receipts/sales-records')
                    ->visibility('private')
                    ->imageEditor()
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) {
                $user = auth()->user();

                $products = collect($data['items'])->map(function (array $item) {
                    $pt = ProductType::find($item['product_type_id']);

                    return [
                        'product_name' => $pt?->name ?? 'Unknown',
                        'grammage' => $item['grammage'],
                        'quantity' => (int) $item['quantity'],
                        'price' => (float) $item['price'],
                    ];
                })->all();

                try {
                    SalesRecordService::submitWarehouseDirectSale([
                        'warehouse_id' => $data['warehouse_id'],
                        'products' => $products,
                        'total_value' => collect($data['items'])->sum(fn (array $item) => (int) $item['quantity'] * (float) $item['price']),
                        'receipt_path' => $data['receipt_path'] ?? null,
                        'customer_name' => $data['customer_name'] ?? null,
                        'customer_phone' => $data['customer_phone'] ?? null,
                    ], $user->id);
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Sale failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Sale recorded')
                    ->body('Stock deducted from the warehouse. The sale is pending accountant approval.')
                    ->success()
                    ->send();

                $this->dispatch('refresh-dashboard');
            })
            ->modalHeading('Record Warehouse Sale')
            ->modalButton('Record Sale');

        $actions[] = Action::make('dispatchRawMaterial')
            ->label('Dispatch Raw Material')
            ->icon('heroicon-o-beaker')
            ->color('warning')
            ->form([
                Select::make('from_warehouse_id')
                    ->label('From Warehouse')
                    ->options(fn (): array => $this->managedWarehouseOptions('raw_material'))
                    ->default(fn (): ?int => $this->currentManagedWarehouseId('raw_material'))
                    ->searchable()
                    ->required()
                    ->live(),
                Select::make('raw_material_id')
                    ->label('Raw Material')
                    ->options(fn () => RawMaterial::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live(),
                TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->minValue(0.0001)
                    ->required()
                    ->helperText(fn (callable $get): ?string => $this->rawMaterialAvailabilityHint($get)),
                Select::make('to_warehouse_id')
                    ->label('Destination Warehouse')
                    ->options(fn (callable $get): array => self::activeModuleWarehousesExcluding((int) $get('from_warehouse_id')))
                    ->default(fn (): ?int => Warehouse::withProductionModule()->where('is_active', true)->orderBy('id')->value('id'))
                    ->searchable()
                    ->required(),
                Textarea::make('notes')
                    ->label('Notes'),
            ])
            ->action(function (array $data) {
                $user = auth()->user();

                try {
                    $transfer = RawMaterialTransferService::requestTransfer(
                        (int) $data['from_warehouse_id'],
                        (int) $data['to_warehouse_id'],
                        (int) $data['raw_material_id'],
                        (float) $data['quantity'],
                        $user->id,
                        $data['notes'] ?? null
                    );

                    RawMaterialTransferService::dispatchTransfer($transfer, $user->id);
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Dispatch failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Raw material dispatched')
                    ->body("Transfer #{$transfer->id} is awaiting receipt at the destination warehouse.")
                    ->success()
                    ->send();

                $this->dispatch('refresh-dashboard');
            })
            ->modalHeading('Dispatch Raw Material')
            ->modalButton('Dispatch');

        $actions[] = Action::make('recordRepack')
            ->label('Repack Stock')
            ->icon('heroicon-o-arrows-right-left')
            ->color('primary')
            ->form([
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): array => $this->managedWarehouseOptions())
                    ->default(fn (): ?int => $this->currentManagedWarehouseId())
                    ->searchable()
                    ->required()
                    ->live(),
                Select::make('input_product_type_id')
                    ->label('From Product (e.g. bulk bags)')
                    ->options(fn () => ProductType::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('input_grammage', null)),
                Select::make('input_grammage')
                    ->label('From Weight')
                    ->options(function (callable $get) {
                        $pt = ProductType::find((int) ($get('input_product_type_id') ?? 0));

                        return $pt
                            ? collect($pt->available_grammages)
                                ->map(fn ($g) => is_array($g) ? $g['grammage'] : $g)
                                ->mapWithKeys(fn ($g) => [(string) $g => ProductType::formatGrammage($pt, (int) $g)])
                                ->toArray()
                            : [];
                    })
                    ->required()
                    ->live(),
                TextInput::make('input_quantity')
                    ->label('From Quantity (pieces)')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->helperText(fn (callable $get): ?string => $this->repackInputAvailabilityHint($get)),
                Select::make('output_product_type_id')
                    ->label('To Product (e.g. 650g packs)')
                    ->options(fn () => ProductType::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('output_grammage', null)),
                Select::make('output_grammage')
                    ->label('To Weight')
                    ->options(function (callable $get) {
                        $pt = ProductType::find((int) ($get('output_product_type_id') ?? 0));

                        return $pt
                            ? collect($pt->available_grammages)
                                ->map(fn ($g) => is_array($g) ? $g['grammage'] : $g)
                                ->mapWithKeys(fn ($g) => [(string) $g => ProductType::formatGrammage($pt, (int) $g)])
                                ->toArray()
                            : [];
                    })
                    ->required(),
                TextInput::make('output_quantity')
                    ->label('To Quantity (pieces)')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(),
                Textarea::make('notes')
                    ->label('Notes')
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) {
                try {
                    $repack = RepackService::post($data, auth()->id());
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Repack failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Stock repacked')
                    ->body("Repack #{$repack->id} posted to inventory.")
                    ->success()
                    ->send();

                $this->dispatch('refresh-dashboard');
            })
            ->modalHeading('Repack Stock')
            ->modalDescription('Convert stock from one SKU into another (e.g. bulk bags into retail packs). Enter the actual input and output pieces; only input availability is checked.')
            ->modalButton('Post Repack');

        $actions[] = Action::make('receiveRawMaterials')
            ->label('Receive Raw Materials')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->form([
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): array => $this->managedWarehouseOptions('raw_material'))
                    ->default(fn (): ?int => $this->currentManagedWarehouseId('raw_material'))
                    ->searchable()
                    ->required()
                    ->live(),
                Radio::make('source_type')
                    ->label('Source')
                    ->options([
                        'supplier' => 'From Supplier (Procurement)',
                        'other' => 'Other Source',
                    ])
                    ->default('supplier')
                    ->required()
                    ->live(),
                TextInput::make('supplier_name')
                    ->label('Supplier Name')
                    ->placeholder('e.g. Supplier ABC')
                    ->visible(fn (callable $get): bool => ($get('source_type') ?? 'supplier') === 'supplier')
                    ->required(fn (callable $get): bool => ($get('source_type') ?? 'supplier') === 'supplier')
                    ->maxLength(200),
                TextInput::make('source_name')
                    ->label('Source Name')
                    ->placeholder('e.g. Donation, return to stock, internal adjustment')
                    ->visible(fn (callable $get): bool => $get('source_type') === 'other')
                    ->required(fn (callable $get): bool => $get('source_type') === 'other')
                    ->maxLength(200),
                FileUpload::make('dispatch_papers')
                    ->label('Dispatch Papers')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                    ->maxSize(10240)
                    ->directory('dispatch-papers')
                    ->multiple()
                    ->visible(fn (callable $get): bool => $get('source_type') === 'other'),
                Repeater::make('items')
                    ->label('Received Items')
                    ->schema([
                        Select::make('raw_material_id')
                            ->label('Raw Material')
                            ->options(fn () => RawMaterial::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(0.0001)
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
                try {
                    $receipt = RawMaterialReceiptService::submit($data, auth()->id());
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Receipt failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Receipt submitted')
                    ->body("Receipt #{$receipt->id} is awaiting approval before stock is added.")
                    ->success()
                    ->send();

                $this->dispatch('refresh-dashboard');
            })
            ->modalHeading('Receive Raw Materials')
            ->modalDescription('Record raw materials from a procurement supplier or another source. Stock is added once the receipt is approved.')
            ->modalButton('Submit Receipt');

        if (Setting::getValue('stock_at_hand_enabled', '0') === '1') {
            $actions[] = Action::make('submitStockCount')
                ->label('Submit Stock Count')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('info')
                ->form([
                    Select::make('warehouse_id')
                        ->label('Warehouse')
                        ->options(fn (): array => $this->managedWarehouseOptions())
                        ->default(fn (): ?int => $this->currentManagedWarehouseId())
                        ->disabled()
                        ->dehydrated()
                        ->required(),
                    Toggle::make('is_additional_count')
                        ->label('This is an additional count (adds to existing stock)')
                        ->default(false)
                        ->live(),
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
                                ->label('Weight')
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
                                        ->mapWithKeys(fn ($g) => [(string) $g => ProductType::formatGrammage($pt, (int) $g)])
                                        ->toArray();
                                })
                                ->required()
                                ->live(),
                            TextInput::make('cartons')
                                ->label(fn (callable $get): string => self::packagingPluralLabel($get))
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->default(0)
                                ->live()
                                ->dehydrated(false)
                                ->afterStateUpdated(fn ($set, callable $get) => self::recalculateReceiveItemTotal($set, $get)),
                            TextInput::make('pieces')
                                ->label('Units')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->default(0)
                                ->live()
                                ->dehydrated(false)
                                ->afterStateUpdated(fn ($set, callable $get) => self::recalculateReceiveItemTotal($set, $get)),
                            TextInput::make('quantity')
                                ->label('Total Pieces')
                                ->numeric()
                                ->integer()
                                ->readOnly()
                                ->default(0)
                                ->minValue(0),
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
                        'is_additional_count' => $data['is_additional_count'] ?? false,
                        'status' => 'pending',
                        'notes' => $data['notes'] ?? null,
                    ]);

                    foreach ($data['items'] as $item) {
                        $pt = ProductType::find($item['product_type_id']);
                        $stockCount->items()->create([
                            'product_type_id' => $item['product_type_id'],
                            'product_name' => $pt?->name ?? 'Unknown Product',
                            'grammage' => $item['grammage'],
                            'quantity' => self::totalPieces($item),
                        ]);
                    }

                    Notification::make()
                        ->title('Stock count submitted')
                        ->body('Your physical stock count is pending accountant approval.')
                        ->success()
                        ->send();

                    $this->dispatch('refresh-dashboard');
                })
                ->modalHeading('Submit Physical Stock Count')
                ->modalButton('Submit Count');
        }

        return $actions;
    }

    protected function exportReport()
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $warehouseIds = auth()->user()->managedWarehouses()->pluck('id');

        $records = StockTransfer::whereIn('from_warehouse_id', $warehouseIds)
            ->where('status', 'dispatched')
            ->with(['toWarehouse', 'toAgent', 'dispatcher', 'items.productType'])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get();

        $filename = 'dispatched_stocks_'.date('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Ref #', 'To Warehouse', 'To Agent', 'Dispatched By', 'Items', 'Date', 'Notes']);

            foreach ($records as $record) {
                $items = $record->items->map(
                    fn ($item) => ($item->productType?->name ?? '').' x'.$item->quantity
                )->implode(', ');

                fputcsv($handle, [
                    $record->id,
                    $record->toWarehouse?->name ?? '-',
                    $record->toAgent?->name ?? '-',
                    $record->dispatcher?->name ?? '-',
                    $items,
                    $record->created_at->format('d/m/Y H:i'),
                    $record->notes ?? '-',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected static function packagingPluralLabel(callable $get): string
    {
        $productType = ProductType::find((int) ($get('product_type_id') ?? 0));
        $grammage = (int) ($get('grammage') ?? 0);

        return ProductType::packagingLabel($productType, $grammage).'s';
    }

    protected static function recalculateReceiveItemTotal(mixed $set, callable $get): void
    {
        $productTypeId = (int) ($get('product_type_id') ?? 0);
        $grammage = (int) ($get('grammage') ?? 0);
        $cartons = (int) ($get('cartons') ?? 0);
        $pieces = (int) ($get('pieces') ?? 0);

        $cartonQuantity = ProductType::find($productTypeId)?->cartonQuantityFor($grammage) ?? 1;

        $set('quantity', $cartons * $cartonQuantity + $pieces);
    }

    protected static function totalPieces(array $item): int
    {
        $cartons = (int) ($item['cartons'] ?? 0);
        $pieces = (int) ($item['pieces'] ?? 0);

        if ($cartons > 0 || $pieces > 0) {
            $cartonQuantity = ProductType::find((int) ($item['product_type_id'] ?? 0))
                ?->cartonQuantityFor((int) ($item['grammage'] ?? 0)) ?? 1;

            return $cartons * $cartonQuantity + $pieces;
        }

        return (int) ($item['quantity'] ?? 0);
    }

    private function rawMaterialAvailabilityHint(callable $get): ?string
    {
        $warehouseId = (int) ($get('from_warehouse_id') ?? 0);
        $materialId = (int) ($get('raw_material_id') ?? 0);

        if (! $warehouseId || ! $materialId) {
            return null;
        }

        $material = RawMaterial::find($materialId);

        if (! $material) {
            return null;
        }

        $available = $material->availableQuantity($warehouseId);

        return "Available at source: {$available} {$material->unit_of_measure}";
    }

    /**
     * @return array<int|string, string>
     */
    private function managedWarehouseOptions(?string $module = null): array
    {
        $base = fn () => Warehouse::where('manager_id', auth()->id())->where('is_active', true);

        $options = $base()
            ->when($module === 'raw_material', fn ($query) => $query->where('has_raw_material_module', true))
            ->orderBy('name')
            ->pluck('name', 'id');

        if ($options->isEmpty() && $module !== null) {
            $options = $base()->orderBy('name')->pluck('name', 'id');
        }

        return $options->all();
    }

    private function currentManagedWarehouseId(?string $module = null): ?int
    {
        $base = fn () => Warehouse::where('manager_id', auth()->id())->where('is_active', true);

        return $base()
            ->when($module === 'raw_material', fn ($query) => $query->where('has_raw_material_module', true))
            ->orderBy('id')
            ->value('id')
            ?? $base()->orderBy('id')->value('id');
    }

    /**
     * @return array<int|string, string>
     */
    public static function activeWarehousesExcluding(?int $excludeId = null): array
    {
        return Warehouse::where('is_active', true)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Active warehouses capable of holding raw materials, optionally
     * excluding one (the dispatch source).
     *
     * @return array<int|string, string>
     */
    public static function activeModuleWarehousesExcluding(?int $excludeId = null): array
    {
        return Warehouse::where('is_active', true)
            ->where(fn ($query) => $query->where('has_production_module', true)->orWhere('has_raw_material_module', true))
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function repackInputAvailabilityHint(callable $get): ?string
    {
        $warehouseId = (int) ($get('warehouse_id') ?? 0);
        $productTypeId = (int) ($get('input_product_type_id') ?? 0);
        $grammage = (int) ($get('input_grammage') ?? 0);

        if (! $warehouseId || ! $productTypeId || ! $grammage) {
            return null;
        }

        $available = Inventory::where([
            'warehouse_id' => $warehouseId,
            'product_type_id' => $productTypeId,
            'grammage' => $grammage,
        ])->value('quantity') ?? 0;

        return "Available: {$available} pcs";
    }
}
