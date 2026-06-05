<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AgentStockBalanceWidget;
use App\Filament\Widgets\AgentStockCardsWidget;
use App\Filament\Widgets\FieldAgentReplaceCustomersWidget;
use App\Filament\Widgets\UpcomingFollowUps;
use App\Models\Inventory;
use App\Models\Lga;
use App\Models\ProductType;
use App\Models\Stockist;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

class AgentDashboard extends BaseDashboard
{
    protected static string $routePath = '/agent-dashboard';

    protected static ?string $slug = 'agent-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['direct_sales', 'open_market', 'retail_market']);
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['direct_sales', 'open_market', 'retail_market']);
    }

    public function getHeaderWidgets(): array
    {
        return [
            AgentStockCardsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        $role = auth()->user()->role;

        $base = [UpcomingFollowUps::class];

        if ($role === 'direct_sales') {
            array_unshift($base, FieldAgentReplaceCustomersWidget::class);
        }

        $base[] = AgentStockBalanceWidget::class;

        return $base;
    }

    protected function getHeaderActions(): array
    {
        $role = auth()->user()->role;
        $actions = [];

        if ($role === 'direct_sales') {
            $actions[] = Action::make('newTrialOrder')
                ->label('New Trial Order')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->url(route('filament.admin.resources.trial-orders.create'));
        }

        if (in_array($role, ['open_market', 'retail_market'])) {
            $actions[] = Action::make('newSalesRecord')
                ->label('New Sales Record')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->url(route('filament.admin.resources.sales-records.create'));
        }

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
                Select::make('stockist_id')
                    ->label('Stockist')
                    ->options(fn () => $this->getStockistOptions())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('from_warehouse_id', null)),

                Select::make('from_warehouse_id')
                    ->label('From Warehouse')
                    ->options(function (callable $get) {
                        $stockistId = $get('stockist_id');
                        if (! $stockistId) {
                            return [];
                        }
                        $stockist = Stockist::find($stockistId);

                        return Warehouse::where(function ($q) use ($stockist) {
                            if ($stockist?->state_id) {
                                $q->where('state_id', $stockist->state_id);
                            }
                            $q->orWhere('type', 'central');
                        })->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required()
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
                // Check warehouse inventory for each requested item
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

                $transfer = StockTransfer::create([
                    'from_warehouse_id' => $data['from_warehouse_id'],
                    'to_stockist_id' => $data['stockist_id'],
                    'requested_by' => auth()->id(),
                    'status' => 'requested',
                    'notes' => $data['notes'] ?? null,
                ]);

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

    private function getStockistOptions(): array
    {
        $user = auth()->user();

        $stateId = null;

        if ($user->lga_id) {
            $lga = Lga::find($user->lga_id);
            $stateId = $lga?->state_id;
        }

        if ($user->role === 'direct_sales' && $user->stockist) {
            $stateId = $user->stockist->state_id;
        }

        if (! $stateId) {
            return Stockist::pluck('name', 'id')->toArray();
        }

        return Stockist::where('state_id', $stateId)->pluck('name', 'id')->toArray();
    }
}
