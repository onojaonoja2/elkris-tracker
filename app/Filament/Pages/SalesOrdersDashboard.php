<?php

namespace App\Filament\Pages;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Filament\Pages\Concerns\HasDashboardBreakdownModals;
use App\Filament\Pages\Concerns\HasDashboardDateFilter;
use App\Filament\Widgets\OrderStatsWidget;
use App\Filament\Widgets\SalesAssignedOrdersWidget;
use App\Filament\Widgets\SalesCsrOverviewWidget;
use App\Filament\Widgets\SalesDamagedReturnWidget;
use App\Filament\Widgets\SalesInventoryStatsWidget;
use App\Filament\Widgets\SalesPendingOrdersWidget;
use App\Filament\Widgets\SalesStockRequestWidget;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductType;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\SalesRecordService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Validation\ValidationException;

class SalesOrdersDashboard extends BaseDashboard
{
    use HasDashboardBreakdownModals;
    use HasDashboardDateFilter;

    protected static string $routePath = '/sales-orders-dashboard';

    protected static ?string $slug = 'sales-orders-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('sales');
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('sales');
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function mount()
    {
        if (! auth()->check() || ! auth()->user()->hasRole('sales')) {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            SalesInventoryStatsWidget::class,
            OrderStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            SalesStockRequestWidget::class,
            SalesPendingOrdersWidget::class,
            SalesAssignedOrdersWidget::class,
            SalesCsrOverviewWidget::class,
            SalesDamagedReturnWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getDateFilterAction(),
            $this->getClearDateFilterAction(),
            $this->getOrderBreakdownAction(),
            $this->buildRequestStockAction(),
            $this->buildRecordOfficeSaleAction(),
            $this->buildInitiateOrderAction(),
            $this->buildSubmitStockCountAction(),
        ];
    }

    private function buildRequestStockAction(): Action
    {
        return Action::make('requestStock')
            ->label('Request Stock')
            ->icon('heroicon-o-shopping-cart')
            ->color('warning')
            ->form([
                Select::make('from_warehouse_id')
                    ->label('From Warehouse')
                    ->options(fn () => Warehouse::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
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
                $transfer = StockTransfer::create([
                    'from_warehouse_id' => $data['from_warehouse_id'],
                    'to_agent_id' => auth()->id(),
                    'requested_by' => auth()->id(),
                    'status' => 'requested',
                    'requires_approval' => true,
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($data['items'] as $item) {
                    $transfer->items()->create($item);
                }

                Notification::make()
                    ->title('Stock request submitted')
                    ->body('Your request is pending accountant approval.')
                    ->success()
                    ->send();
            })
            ->modalHeading('Request Stock from Warehouse')
            ->modalButton('Submit Request');
    }

    private function buildRecordOfficeSaleAction(): Action
    {
        return Action::make('recordOfficeSale')
            ->label('Record Office Sale')
            ->icon('heroicon-o-currency-dollar')
            ->color('success')
            ->form([
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
                TextInput::make('price')
                    ->label('Unit Price (₦)')
                    ->numeric()
                    ->minValue(0)
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
                $pt = ProductType::find($data['product_type_id']);
                $lineTotal = (int) $data['quantity'] * (float) $data['price'];

                try {
                    SalesRecordService::submitSale([
                        'agent_type' => 'sales',
                        'products' => [
                            [
                                'product_name' => $pt->name,
                                'grammage' => $data['grammage'],
                                'quantity' => (int) $data['quantity'],
                                'price' => (float) $data['price'],
                            ],
                        ],
                        'total_value' => $lineTotal,
                        'receipt_path' => $data['receipt_path'] ?? null,
                        'vendor_name' => $data['customer_name'] ?? null,
                        'customer_name' => $data['customer_name'] ?? null,
                        'customer_phone' => $data['customer_phone'] ?? null,
                    ], $user->id);
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Insufficient stock')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Office sale submitted')
                    ->body("Sale of {$data['quantity']}x {$pt->name} ({$data['grammage']}g) submitted for accountant approval.")
                    ->success()
                    ->send();
            })
            ->modalHeading('Record Office Sale')
            ->modalButton('Record Sale');
    }

    private function buildInitiateOrderAction(): Action
    {
        return Action::make('initiateOrder')
            ->label('Initiate Order')
            ->icon('heroicon-o-document-plus')
            ->color('primary')
            ->form([
                Select::make('customer_id')
                    ->label('Customer')
                    ->options(fn () => Customer::pluck('customer_name', 'id'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('new_customer_name', null)),
                TextInput::make('new_customer_name')
                    ->label('Or create new customer name')
                    ->hidden(fn (callable $get) => filled($get('customer_id')))
                    ->required(fn (callable $get) => ! filled($get('customer_id'))),
                TextInput::make('new_customer_phone')
                    ->label('New Customer Phone')
                    ->tel()
                    ->hidden(fn (callable $get) => filled($get('customer_id')))
                    ->required(fn (callable $get) => ! filled($get('customer_id'))),
                Repeater::make('items')
                    ->label('Order Items')
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
                Textarea::make('notes')
                    ->label('Order Notes'),
            ])
            ->action(function (array $data) {
                $user = auth()->user();

                $customerId = $data['customer_id'];
                if (! $customerId && filled($data['new_customer_name'] ?? null)) {
                    $customer = Customer::create([
                        'agent_id' => $user->id,
                        'customer_name' => $data['new_customer_name'],
                        'phone_number' => $data['new_customer_phone'] ?? null,
                    ]);
                    $customerId = $customer->id;
                }

                $totalPrice = collect($data['items'])->sum(fn ($item) => (int) $item['quantity'] * (float) $item['price']);

                $order = Order::create([
                    'customer_id' => $customerId,
                    'user_id' => $user->id,
                    'status' => OrderStatus::Pending,
                    'total_price' => $totalPrice,
                    'assignment_status' => AssignmentStatus::None,
                ]);

                foreach ($data['items'] as $item) {
                    $pt = ProductType::find($item['product_type_id']);
                    $order->products()->create([
                        'product_type_id' => $item['product_type_id'],
                        'product_name' => $pt->name,
                        'grammage' => $item['grammage'],
                        'quantity' => (int) $item['quantity'],
                        'price' => (float) $item['price'],
                    ]);
                }

                Notification::make()
                    ->title('Order initiated')
                    ->body("Order #{$order->id} created successfully.")
                    ->success()
                    ->send();
            })
            ->modalHeading('Initiate New Order')
            ->modalButton('Create Order');
    }

    private function buildSubmitStockCountAction(): Action
    {
        return Action::make('submitStockCount')
            ->label('Submit Stock Count')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('info')
            ->form([
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
                $isAdditional = $data['is_additional_count'] ?? false;

                $stockCount = StockCount::create([
                    'user_id' => auth()->id(),
                    'is_additional_count' => $isAdditional,
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
}
