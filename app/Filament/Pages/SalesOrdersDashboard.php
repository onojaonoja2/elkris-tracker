<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SalesAssignedOrdersWidget;
use App\Filament\Widgets\SalesCsrOverviewWidget;
use App\Filament\Widgets\SalesDamagedReturnWidget;
use App\Filament\Widgets\SalesInventoryStatsWidget;
use App\Filament\Widgets\SalesPendingOrdersWidget;
use App\Filament\Widgets\SalesStockRequestWidget;
use App\Models\ProductType;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

class SalesOrdersDashboard extends BaseDashboard
{
    protected static string $routePath = '/sales-orders-dashboard';

    protected static ?string $slug = 'sales-orders-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'sales';
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'sales';
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function mount()
    {
        if (! auth()->check() || auth()->user()->role !== 'sales') {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            SalesInventoryStatsWidget::class,
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
            Action::make('requestStock')
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
                ->modalButton('Submit Request'),
        ];
    }
}
