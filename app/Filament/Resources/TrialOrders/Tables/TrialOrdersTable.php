<?php

namespace App\Filament\Resources\TrialOrders\Tables;

use App\Filament\Exports\TrialOrderExporter;
use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
use App\Models\TrialOrder;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TrialOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('agent.name')
                    ->label('Field Agent')
                    ->searchable()
                    ->sortable()
                    ->visible(fn ($record) => $record && $record->agent_id !== null),
                TextColumn::make('stockist.name')
                    ->label('Stockist')
                    ->searchable()
                    ->sortable()
                    ->visible(fn ($record) => $record && $record->stockist_id !== null),
                TextColumn::make('agent.assigned_cities')
                    ->label('Location')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->toggleable(),
                TextColumn::make('state')
                    ->label('State')
                    ->state(fn (TrialOrder $record): ?string => self::getStateFromOrder($record))
                    ->sortable(),
                TextColumn::make('total_value')
                    ->label('Total Value (₦)')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('products')
                    ->label('Products')
                    ->formatStateUsing(fn ($products) => collect($products)->map(fn ($p) => "{$p['quantity']}x {$p['product_name']}")->implode(', '))
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state, TrialOrder $record): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => $record->isLocked() ? 'success' : 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state, TrialOrder $record): string => $record->isLocked() ? 'Locked' : ucfirst($state)),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'completed' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('stockist.name')
                    ->label('Linked Stockist')
                    ->placeholder('N/A')
                    ->visible(fn (?TrialOrder $record) => $record?->stockist_id !== null)
                    ->toggleable(),
                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->placeholder('N/A')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('From Date')
                            ->closeOnDateSelection(),
                        DatePicker::make('created_until')
                            ->label('To Date')
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(TrialOrderExporter::class),
            ])
            ->recordActions([
                Action::make('confirmPayment')
                    ->label('Confirm Payment')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('info')
                    ->visible(fn ($record) => $record->payment_status === 'pending' && in_array(auth()->user()->role, ['supervisor', 'admin']))
                    ->form([
                        Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                'cash' => 'Cash',
                                'transfer' => 'Bank Transfer',
                                'pos' => 'POS',
                            ])
                            ->required(),
                        Select::make('balance_holder')
                            ->label('Hold Balance With')
                            ->options([
                                'agent' => 'Field Agent',
                                'stockist' => 'Stockist',
                            ])
                            ->default('agent')
                            ->required()
                            ->live(),
                        Select::make('stockist_id')
                            ->label('Select Stockist')
                            ->options(function ($get) {
                                $options = [];
                                $stockists = Stockist::where('supervisor_id', auth()->id())->get();
                                foreach ($stockists as $stockist) {
                                    $canFulfill = true;
                                    $stockList = [];
                                    foreach ($get('products') ?? [] as $product) {
                                        if (empty($product['product_name']) || empty($product['grammage'])) {
                                            continue;
                                        }
                                        $stock = StockistStock::where('stockist_id', $stockist->id)
                                            ->where('product_name', $product['product_name'])
                                            ->where('grammage', $product['grammage'])
                                            ->first();
                                        if (! $stock || $stock->quantity < ($product['quantity'] ?? 0)) {
                                            $canFulfill = false;
                                            break;
                                        }
                                        $stockList[] = "{$product['product_name']} ({$product['grammage']}g)";
                                    }
                                    if ($canFulfill && count($stockList) > 0) {
                                        $options[$stockist->id] = $stockist->name.' - '.implode(', ', $stockList);
                                    }
                                }

                                return $options;
                            })
                            ->visible(fn ($get) => $get('balance_holder') === 'stockist')
                            ->required(fn ($get) => $get('balance_holder') === 'stockist'),
                    ])
                    ->action(function ($record, array $data) {
                        // Process payment directly
                        $balanceHolder = $data['balance_holder'] ?? 'agent';
                        $paymentMethod = $data['payment_method'] ?? 'cash';
                        $selectedStockistId = $data['stockist_id'] ?? null;

                        $agent = $record->agent;
                        $products = $record->products ?? [];

                        $stockist = null;

                        if ($balanceHolder === 'stockist' && $selectedStockistId) {
                            $stockist = Stockist::find($selectedStockistId);
                        }

                        if (! $stockist && $balanceHolder === 'agent') {
                            $stockist = Stockist::where('supervisor_id', auth()->id())
                                ->whereIn('city', (array) ($agent?->assigned_cities ?? []))
                                ->first();
                        }

                        if (! $stockist) {
                            Notification::make()
                                ->danger()
                                ->title('No stockist found')
                                ->body('No stockist found with available stock. Please select a stockist with sufficient inventory.')
                                ->send();

                            return;
                        }

                        DB::transaction(function () use ($stockist, $products, $record, $balanceHolder, $paymentMethod, $agent) {
                            foreach ($products as $product) {
                                $productName = $product['product_name'] ?? null;
                                $grammage = $product['grammage'] ?? null;
                                $quantity = $product['quantity'] ?? 0;

                                if ($productName && $grammage && $quantity > 0) {
                                    $stockistStock = StockistStock::firstOrCreate(
                                        [
                                            'stockist_id' => $stockist->id,
                                            'product_name' => $productName,
                                            'grammage' => $grammage,
                                        ],
                                        [
                                            'quantity' => 0,
                                        ]
                                    );

                                    $stockistStock = StockistStock::where('id', $stockistStock->id)
                                        ->lockForUpdate()
                                        ->first();

                                    if ($stockistStock->quantity < $quantity) {
                                        throw new \Exception("Insufficient stock: {$productName} ({$grammage}g). Available: {$stockistStock->quantity}, Requested: {$quantity}");
                                    }

                                    $stockistStock->decrement('quantity', $quantity);
                                }
                            }

                            $updateData = [
                                'payment_status' => 'completed',
                                'status' => 'approved',
                                'approved_by' => auth()->id(),
                                'stockist_id' => $stockist->id,
                            ];

                            if ($balanceHolder === 'agent' && $agent) {
                                $updateData['agent_balance'] = $record->total_value;
                                $updateData['stockist_balance'] = 0;
                                $agent->increment('stock_balance', $record->total_value);
                            } else {
                                $updateData['agent_balance'] = 0;
                                $updateData['stockist_balance'] = $record->total_value;
                                $stockist->decrement('stock_balance', $record->total_value);
                            }

                            $record->update($updateData);

                            StockistTransaction::create([
                                'stockist_id' => $stockist->id,
                                'user_id' => auth()->id(),
                                'field_agent_id' => $agent?->id,
                                'trial_order_id' => $record->id,
                                'type' => 'deducted',
                                'amount' => $record->total_value,
                                'description' => "Trial order approved - Payment via {$paymentMethod}, Balance held with {$balanceHolder}",
                                'transaction_date' => now()->toDateString(),
                            ]);
                        });
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Payment Received')
                    ->modalDescription('This will confirm payment, deduct stock from the appropriate stockist, and lock the trial order.')
                    ->modalButton('Confirm'),
            ]);
    }

    public static function getCityStateMapping(): array
    {
        return [
            'lagos_island' => 'Lagos',
            'ikorodu' => 'Lagos',
            'epe' => 'Lagos',
            'ibadan' => 'Oyo',
            'ogbomosho' => 'Oyo',
            'oyo' => 'Oyo',
            'ife' => 'Osun',
            'ilesa' => 'Osun',
            'iwo' => 'Osun',
            'osogbo' => 'Osun',
            'abeokuta' => 'Ogun',
            'sagamu' => 'Ogun',
            'ijebu_ode' => 'Ogun',
            'benin_city' => 'Edo',
            'auchi' => 'Edo',
            'uromi' => 'Edo',
            'ekpoma' => 'Edo',
            'warri' => 'Delta',
            'sapele' => 'Delta',
            'asaba' => 'Delta',
            'uyo' => 'Akwa Ibom',
            'ikot_ekpeme' => 'Akwa Ibom',
            'port_harcourt' => 'Rivers',
            'buguma' => 'Rivers',
            'calabar' => 'Cross River',
            'ugeb' => 'Cross River',
            'aba' => 'Abia',
            'umuahia' => 'Abia',
            'enugu' => 'Enugu',
            'nsukka' => 'Enugu',
            'awka' => 'Anambra',
            'okpoko' => 'Anambra',
            'owerri' => 'Imo',
            'okigwe' => 'Imo',
            'abakaliki' => 'Ebonyi',
            'minna' => 'Niger',
            'mokwa' => 'Niger',
            'bida' => 'Niger',
            'suleja' => 'Niger',
            'ilorin' => 'Kwara',
            'abuja' => 'FCT',
        ];
    }

    public static function approveTrialOrder($record, array $data): void
    {
        // This method is no longer used - stock deduction happens in confirmPayment
        // Kept for backwards compatibility
    }

    public static function getStateFromAgent($record): ?string
    {
        $agent = $record->agent;
        if (! $agent) {
            return null;
        }

        $agentCities = is_array($agent->assigned_cities) ? $agent->assigned_cities : [];
        if (empty($agentCities)) {
            return null;
        }

        $firstCity = $agentCities[0];
        $stockist = Stockist::where('city', $firstCity)->first();

        return $stockist?->state;
    }

    public static function getStateFromOrder($record): ?string
    {
        if ($record->stockist_id !== null) {
            return $record->stockist?->state;
        }

        if ($record->agent_id !== null) {
            return self::getStateFromAgent($record);
        }

        return null;
    }
}
