<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AgentStockBalanceWidget;
use App\Filament\Widgets\AgentStockCardsWidget;
use App\Filament\Widgets\FieldAgentDailySubmissionsWidget;
use App\Filament\Widgets\FieldAgentReplaceCustomersWidget;
use App\Filament\Widgets\UpcomingFollowUps;
use App\Models\Inventory;
use App\Models\Lga;
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

class AgentDashboard extends BaseDashboard
{
    protected static string $routePath = '/agent-dashboard';

    protected static ?string $slug = 'agent-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['community_sales_representative', 'open_market', 'retail_market']);
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['community_sales_representative', 'open_market', 'retail_market']);
    }

    public function getHeaderWidgets(): array
    {
        return [
            FieldAgentDailySubmissionsWidget::class,
            AgentStockCardsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        $role = auth()->user()->role;

        $base = [UpcomingFollowUps::class];

        if ($role === 'community_sales_representative') {
            array_unshift($base, FieldAgentReplaceCustomersWidget::class);
        }

        $base[] = AgentStockBalanceWidget::class;

        return $base;
    }

    protected function getHeaderActions(): array
    {
        $role = auth()->user()->role;
        $actions = [];

        if ($role === 'community_sales_representative') {
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
                Select::make('source_type')
                    ->label('Source Type')
                    ->options([
                        'warehouse' => 'From Warehouse',
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
        $user = auth()->user();

        $stateId = null;

        if ($user->lga_id) {
            $lga = Lga::find($user->lga_id);
            $stateId = $lga?->state_id;
        }

        return Warehouse::where(function ($q) use ($stateId) {
            $q->where('name', 'like', '%Central Lagos%')
                ->orWhere('name', 'like', '%Abuja%');

            if ($stateId) {
                $q->orWhere('state_id', $stateId);
            }
        })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->unique()
            ->toArray();
    }
}
