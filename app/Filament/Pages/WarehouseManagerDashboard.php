<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\WarehouseManagerStatsWidget;
use App\Filament\Widgets\WarehouseRecentMovementsWidget;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

class WarehouseManagerDashboard extends BaseDashboard
{
    protected static string $routePath = '/warehouse-dashboard';

    protected static ?string $slug = 'warehouse-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'warehouse_manager';
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'warehouse_manager';
    }

    public function mount()
    {
        if (! auth()->check() || auth()->user()->role !== 'warehouse_manager') {
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
                            TextInput::make('supplier')
                                ->label('Supplier / Source'),
                        ])
                        ->addActionLabel('Add Item')
                        ->defaultItems(1)
                        ->minItems(1)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    $warehouse = Warehouse::find($data['warehouse_id']);

                    foreach ($data['items'] as $item) {
                        $transaction = StockTransaction::create([
                            'type' => 'received',
                            'product_name' => ProductType::find($item['product_type_id'])?->name ?? 'Unknown',
                            'grammage' => $item['grammage'],
                            'quantity' => $item['quantity'],
                            'user_id' => $user->id,
                            'transaction_date' => now()->toDateString(),
                            'disbursed_to' => $item['supplier'] ?? null,
                        ]);

                        $inv = Inventory::firstOrCreate(
                            [
                                'warehouse_id' => $warehouse->id,
                                'product_type_id' => $item['product_type_id'],
                                'grammage' => $item['grammage'],
                            ],
                            ['quantity' => 0]
                        );
                        $inv->increment('quantity', $item['quantity']);

                        $pdf = Pdf::loadView('pdf.goods-received-note', [
                            'transaction' => $transaction,
                            'warehouse' => $warehouse,
                        ]);

                        $pdfPath = storage_path('app/public/grn-'.$transaction->id.'.pdf');
                        $pdf->save($pdfPath);
                    }

                    Notification::make()->title('Stock received successfully')->success()->send();
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
                    $transferData = [
                        'from_warehouse_id' => $data['from_warehouse_id'],
                        'dispatched_by' => $user->id,
                        'status' => 'dispatched',
                        'notes' => $data['notes'] ?? null,
                    ];

                    if (($data['to_type'] ?? null) === 'warehouse') {
                        $transferData['to_warehouse_id'] = $data['to_warehouse_id'];
                    }

                    $transfer = StockTransfer::create($transferData);

                    foreach ($data['items'] as $item) {
                        $transfer->items()->create($item);

                        $inv = Inventory::where([
                            'warehouse_id' => $data['from_warehouse_id'],
                            'product_type_id' => $item['product_type_id'],
                            'grammage' => $item['grammage'],
                        ])->first();

                        if ($inv) {
                            $inv->decrement('quantity', $item['quantity']);
                        }
                    }

                    $pdf = Pdf::loadView('pdf.dispatch-note', ['transfer' => $transfer]);
                    $pdfPath = storage_path('app/public/dispatch-'.$transfer->id.'.pdf');
                    $pdf->save($pdfPath);

                    Notification::make()->title('Stock dispatched successfully')->success()->send();
                }),
        ];
    }
}
