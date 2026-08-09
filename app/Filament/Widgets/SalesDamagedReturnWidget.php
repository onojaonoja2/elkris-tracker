<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\DamagedStockReturn;
use App\Models\ProductType;
use App\Support\WarehouseOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class SalesDamagedReturnWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'Damaged Stock Returns';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => DamagedStockReturn::where('user_id', auth()->id())->orderBy('created_at', 'desc'))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse'),

                TextColumn::make('productType.name')
                    ->label('Product'),

                TextColumn::make('grammage')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state.'g'),

                TextColumn::make('quantity')
                    ->label('Qty'),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(40),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->headerActions([
                Action::make('returnDamaged')
                    ->label('Return Damaged Stock')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->form([
                        Select::make('warehouse_id')
                            ->label('Return To Warehouse')
                            ->options(fn () => WarehouseOptions::for())
                            ->searchable()
                            ->required(),
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
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        Textarea::make('reason')
                            ->label('Reason for Return')
                            ->rows(3),
                    ])
                    ->action(function (array $data) {
                        $userId = auth()->id();

                        $availableStock = AgentStock::where('user_id', $userId)
                            ->where('product_type_id', $data['product_type_id'])
                            ->where('grammage', $data['grammage'])
                            ->sum('quantity');

                        if ($data['quantity'] > $availableStock) {
                            Notification::make()
                                ->title('Insufficient stock')
                                ->body("You only have {$availableStock} units of this product. Cannot return {$data['quantity']} as damaged.")
                                ->danger()
                                ->send();

                            return;
                        }

                        DamagedStockReturn::create([
                            'user_id' => $userId,
                            'warehouse_id' => $data['warehouse_id'],
                            'product_type_id' => $data['product_type_id'],
                            'grammage' => $data['grammage'],
                            'quantity' => $data['quantity'],
                            'reason' => $data['reason'] ?? null,
                            'status' => 'pending',
                        ]);

                        Notification::make()->title('Damaged stock return submitted')->success()->send();

                        $this->dispatch('refresh-dashboard');
                    }),
            ])
            ->paginated(10);
    }
}
