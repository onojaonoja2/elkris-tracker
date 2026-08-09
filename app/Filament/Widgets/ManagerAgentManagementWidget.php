<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\ProductType;
use App\Models\SalesRecord;
use App\Models\StockTransfer;
use App\Models\User;
use App\Notifications\AccountStatusNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class ManagerAgentManagementWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Field Agents (CSR / Open Market / Retail)';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        $agentRoles = ['community_sales_representative', 'open_market', 'retail_market'];
        $agentIds = User::whereIn('role', $agentRoles)->pluck('id');

        $stockCounts = AgentStock::whereIn('user_id', $agentIds)
            ->selectRaw('user_id, SUM(quantity) as total_qty')
            ->groupBy('user_id')
            ->pluck('total_qty', 'user_id');

        $salesCounts = SalesRecord::whereIn('agent_id', $agentIds)
            ->where('status', 'approved')
            ->selectRaw('agent_id, COUNT(*) as count, COALESCE(SUM(total_value), 0) as total_value')
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');

        $revenueByAgent = SalesRecord::revenueByAgent($agentIds->all(), now()->startOfYear(), now());

        return $table
            ->query(
                fn () => User::whereIn('role', $agentRoles)
                    ->with(['state', 'lga'])
                    ->orderBy('name')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Agent Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('role')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'community_sales_representative' => 'CSR',
                        'open_market' => 'Open Market',
                        'retail_market' => 'Retail Market',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'community_sales_representative' => 'info',
                        'open_market' => 'warning',
                        'retail_market' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('state.name')
                    ->label('State')
                    ->searchable(),

                TextColumn::make('lga.name')
                    ->label('LGA')
                    ->searchable(),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Suspended'),

                TextColumn::make('stock_units')
                    ->label('Stock')
                    ->getStateUsing(fn (User $record): int => $stockCounts->get($record->id, 0))
                    ->sortable(),

                TextColumn::make('approved_sales_count')
                    ->label('Sales')
                    ->getStateUsing(fn (User $record): int => $salesCounts->get($record->id)?->count ?? 0)
                    ->sortable(),

                TextColumn::make('approved_sales_value')
                    ->label('Revenue')
                    ->getStateUsing(fn (User $record): string => '₦'.number_format($revenueByAgent->get($record->id)?->revenue ?? 0, 2))
                    ->money('NGN')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('collectStock')
                    ->label('Collect Stock')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('warning')
                    ->size('sm')
                    ->visible(fn (User $record): bool => $record->is_active)
                    ->form([
                        Repeater::make('items')
                            ->label('Stock to Collect')
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
                                    ->options(function ($get) {
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

                                Textarea::make('stock_note')
                                    ->label('Note')
                                    ->rows(1)
                                    ->columnSpanFull(),
                            ])
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Add Item'),
                        Textarea::make('notes')
                            ->label('Collection Notes'),
                    ])
                    ->action(function (User $record, array $data) {
                        $agentStocks = AgentStock::where('user_id', $record->id)->get();

                        $items = collect($data['items'])->filter(fn ($item) => ! empty($item['product_type_id']) && ! empty($item['grammage']));

                        if ($items->isEmpty()) {
                            Notification::make()
                                ->title('No items selected')
                                ->warning()
                                ->send();

                            return;
                        }

                        $transfer = StockTransfer::create([
                            'from_agent_id' => $record->id,
                            'requested_by' => auth()->id(),
                            'status' => 'collected',
                            'source_type' => 'agent_collection',
                            'notes' => $data['notes'] ?? null,
                        ]);

                        foreach ($items as $item) {
                            $transfer->items()->create([
                                'product_type_id' => $item['product_type_id'],
                                'grammage' => $item['grammage'],
                                'quantity' => $agentStocks
                                    ->where('product_type_id', $item['product_type_id'])
                                    ->where('grammage', $item['grammage'])
                                    ->sum('quantity'),
                            ]);
                        }

                        Notification::make()
                            ->title('Stock collected')
                            ->body("Stock collected from {$record->name}. You can now re-assign it.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('collectAllStock')
                    ->label('Collect All Stock')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('warning')
                    ->size('sm')
                    ->visible(fn (User $record): bool => $record->is_active && AgentStock::where('user_id', $record->id)->sum('quantity') > 0)
                    ->requiresConfirmation()
                    ->modalHeading('Collect All Stock')
                    ->modalDescription(fn (User $record): string => "This will collect ALL stock from {$record->name}. Their stock balance will be transferred to a holding state.")
                    ->action(function (User $record) {
                        $agentStocks = AgentStock::where('user_id', $record->id)->where('quantity', '>', 0)->get();

                        if ($agentStocks->isEmpty()) {
                            Notification::make()
                                ->title('No stock to collect')
                                ->warning()
                                ->send();

                            return;
                        }

                        $transfer = StockTransfer::create([
                            'from_agent_id' => $record->id,
                            'requested_by' => auth()->id(),
                            'status' => 'collected',
                            'source_type' => 'agent_collection',
                            'notes' => 'Full stock collection from agent',
                        ]);

                        foreach ($agentStocks as $stock) {
                            $transfer->items()->create([
                                'product_type_id' => $stock->product_type_id,
                                'grammage' => $stock->grammage,
                                'quantity' => $stock->quantity,
                            ]);
                        }

                        Notification::make()
                            ->title('All stock collected')
                            ->body("All stock collected from {$record->name}.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->size('sm')
                    ->visible(fn (User $record): bool => $record->is_active)
                    ->form([
                        Textarea::make('reason')
                            ->label('Reason for Suspension')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Suspend Agent')
                    ->modalDescription(fn (User $record): string => "Are you sure you want to suspend {$record->name}? Their stock will be automatically collected.")
                    ->modalButton('Suspend & Collect Stock')
                    ->action(function (User $record, array $data) {
                        $manager = auth()->user();

                        $agentStocks = AgentStock::where('user_id', $record->id)->where('quantity', '>', 0)->get();

                        if ($agentStocks->isNotEmpty()) {
                            $transfer = StockTransfer::create([
                                'from_agent_id' => $record->id,
                                'requested_by' => auth()->id(),
                                'status' => 'collected',
                                'source_type' => 'agent_collection',
                                'notes' => "Auto-collected on suspension: {$data['reason']}",
                            ]);

                            foreach ($agentStocks as $stock) {
                                $transfer->items()->create([
                                    'product_type_id' => $stock->product_type_id,
                                    'grammage' => $stock->grammage,
                                    'quantity' => $stock->quantity,
                                ]);
                            }
                        }

                        $record->suspend($data['reason']);

                        $record->notify(new AccountStatusNotification(
                            action: 'suspended',
                            managerName: $manager->name,
                            reason: $data['reason'],
                        ));

                        Notification::make()
                            ->title('Agent Suspended')
                            ->body("{$record->name}'s account has been suspended."
                                .($agentStocks->isNotEmpty() ? ' Their stock has been automatically collected.' : ''))
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),

                Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->size('sm')
                    ->visible(fn (User $record): bool => ! $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Reactivate Agent')
                    ->modalDescription(fn (User $record): string => "Are you sure you want to reactivate {$record->name}?")
                    ->action(function (User $record) {
                        $manager = auth()->user();
                        $record->reactivate();

                        $record->notify(new AccountStatusNotification(
                            action: 'reactivated',
                            managerName: $manager->name,
                        ));

                        Notification::make()
                            ->title('Agent Reactivated')
                            ->body("{$record->name}'s account has been reactivated.")
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    }),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50, -1]);
    }
}
