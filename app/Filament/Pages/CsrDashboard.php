<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AgentCreditSalesWidget;
use App\Filament\Widgets\CsrAssignedOrdersWidget;
use App\Filament\Widgets\CsrPendingDispatchesWidget;
use App\Filament\Widgets\CsrSalesRecordsWidget;
use App\Filament\Widgets\CsrStatsWidget;
use App\Filament\Widgets\CsrStocksWidget;
use App\Filament\Widgets\DamagedStockReturnFormWidget;
use App\Models\ProductType;
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

class CsrDashboard extends BaseDashboard
{
    protected static string $routePath = '/csr-dashboard';

    protected static ?string $slug = 'csr-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'community_sales_representative';
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'community_sales_representative';
    }

    public function getHeaderWidgets(): array
    {
        return [
            CsrStatsWidget::class,
            AgentCreditSalesWidget::class,
            CsrStocksWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            CsrAssignedOrdersWidget::class,
            CsrPendingDispatchesWidget::class,
            CsrSalesRecordsWidget::class,
            DamagedStockReturnFormWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        $actions[] = Action::make('newSalesRecord')
            ->label('New Sales Record')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->url(route('filament.admin.resources.sales-records.create'));

        $actions[] = Action::make('newCustomer')
            ->label('New Customer')
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->url(route('filament.admin.resources.customers.create'));

        $actions[] = $this->getRequestStockAction();

        return $actions;
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
                        'csr_peer' => 'From CSR Peer',
                    ])
                    ->default('warehouse')
                    ->required()
                    ->live(),

                Select::make('from_warehouse_id')
                    ->label('From Warehouse')
                    ->options(fn () => $this->getWarehouseOptions())
                    ->searchable()
                    ->required()
                    ->visible(fn (callable $get) => $get('source_type') === 'warehouse')
                    ->live(),

                Select::make('peer_scope')
                    ->label('Request From')
                    ->options([
                        'lga' => 'Same LGA',
                        'state' => 'Same State',
                        'country' => 'Country Wide',
                    ])
                    ->default('lga')
                    ->required()
                    ->visible(fn (callable $get) => $get('source_type') === 'csr_peer')
                    ->live(),

                Select::make('from_agent_id')
                    ->label('Select CSR')
                    ->options(fn (callable $get) => $this->getCsrPeerOptions($get('peer_scope')))
                    ->searchable()
                    ->required()
                    ->visible(fn (callable $get) => $get('source_type') === 'csr_peer')
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
                if ($data['source_type'] === 'warehouse') {
                    $transfer = StockTransfer::create([
                        'from_warehouse_id' => $data['from_warehouse_id'],
                        'to_agent_id' => auth()->id(),
                        'requested_by' => auth()->id(),
                        'status' => 'requested',
                        'notes' => $data['notes'] ?? null,
                    ]);
                } else {
                    $transfer = StockTransfer::create([
                        'from_agent_id' => $data['from_agent_id'],
                        'to_agent_id' => auth()->id(),
                        'requested_by' => auth()->id(),
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

    private function getWarehouseOptions(): array
    {
        return Warehouse::orderBy('name')->pluck('name', 'id')->toArray();
    }

    private function getCsrPeerOptions(string $scope = 'lga'): array
    {
        $user = auth()->user();

        $query = User::where('role', 'community_sales_representative')
            ->where('id', '!=', $user->id);

        match ($scope) {
            'lga' => $query->where('lga_id', $user->lga_id),
            'state' => $query->where('state_id', $user->state_id),
            'country' => null,
            default => null,
        };

        return $query->pluck('name', 'id')->toArray();
    }
}
