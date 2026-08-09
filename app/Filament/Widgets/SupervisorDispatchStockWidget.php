<?php

namespace App\Filament\Widgets;

use App\Enums\StockTransferStatus;
use App\Filament\Traits\HasBreakdownViewAction;
use App\Models\ProductType;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\WarehouseOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class SupervisorDispatchStockWidget extends TableWidget
{
    use HasBreakdownViewAction;

    protected static ?string $heading = 'Stock Dispatches to CSRs';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 6;

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasRole('supervisor');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn () => StockTransfer::where('source_type', 'supervisor_dispatch')
                    ->with(['fromWarehouse', 'toAgent', 'items.productType'])
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('fromWarehouse.name')
                    ->label('From Warehouse')
                    ->placeholder('N/A'),
                TextColumn::make('toAgent.name')
                    ->label('To CSR')
                    ->placeholder('N/A'),
                TextColumn::make('items')
                    ->label('Items')
                    ->formatStateUsing(fn (StockTransfer $record): string => $record->items
                        ->map(fn ($item) => "{$item->quantity}x {$item->productType?->name} ({$item->grammage}g)")
                        ->implode(', '))
                    ->limit(60),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->placeholder('-')
                    ->limit(40),
            ])
            ->recordActions([
                $this->breakdownViewAction(),
            ])
            ->headerActions([
                Action::make('dispatchStock')
                    ->label('Dispatch Stock to CSR')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->form([
                        Select::make('from_warehouse_id')
                            ->label('From Warehouse')
                            ->options(fn (): array => WarehouseOptions::for())
                            ->searchable()
                            ->required()
                            ->live(),
                        Select::make('to_agent_id')
                            ->label('To CSR')
                            ->options(fn (): array => User::where('role', 'community_sales_representative')
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                        Repeater::make('items')
                            ->label('Stock Items')
                            ->schema([
                                Select::make('product_type_id')
                                    ->label('Product')
                                    ->options(fn (): array => ProductType::where('is_active', true)->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn ($set) => $set('grammage', null)),
                                Select::make('grammage')
                                    ->label('Weight (g)')
                                    ->options(function ($get): array {
                                        $productTypeId = $get('product_type_id');

                                        if (blank($productTypeId)) {
                                            return [];
                                        }

                                        $productType = ProductType::find($productTypeId);

                                        if (! $productType) {
                                            return [];
                                        }

                                        return collect($productType->available_grammages)
                                            ->map(fn ($g) => is_array($g) ? $g['grammage'] : $g)
                                            ->mapWithKeys(fn ($g) => [(string) $g => $g.'g'])
                                            ->all();
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
                        $transfer = StockTransfer::create([
                            'from_warehouse_id' => $data['from_warehouse_id'],
                            'to_agent_id' => $data['to_agent_id'],
                            'requested_by' => auth()->id(),
                            'status' => StockTransferStatus::Requested,
                            'source_type' => 'supervisor_dispatch',
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
                            ->title('Dispatch request submitted')
                            ->body('Awaiting accountant verification before stock is moved to the CSR.')
                            ->success()
                            ->send();

                        $this->dispatch('refresh-dashboard');
                    })
                    ->modalHeading('Dispatch Stock to CSR')
                    ->modalButton('Submit Dispatch'),
            ])
            ->paginated([10, 25, 50]);
    }
}
