<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\ProductType;
use App\Models\StockTransfer;
use App\Models\Warehouse;
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

class SalesStockRequestWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'My Stock Balance';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        $userId = auth()->id();

        $stockQuery = AgentStock::where('user_id', $userId)
            ->with('productType')
            ->orderBy('product_name')
            ->orderBy('grammage');

        return $table
            ->query(fn () => $stockQuery)
            ->columns([
                TextColumn::make('product_name')
                    ->label('Product')
                    ->searchable(),

                TextColumn::make('grammage')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => "{$state}g"),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
            ])
            ->defaultSort('product_name');
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('requestStock')
                ->label('Request Stock from Warehouse')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->form([
                    Select::make('warehouse_id')
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
                    $transfer = StockTransfer::create([
                        'from_warehouse_id' => $data['warehouse_id'],
                        'to_agent_id' => auth()->id(),
                        'requested_by' => auth()->id(),
                        'status' => 'requested',
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
                        ->title('Stock request submitted')
                        ->body('Your stock request is pending accountant approval.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
