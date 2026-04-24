<?php

namespace App\Filament\Resources\TrialOrders\Tables;

use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
use App\Models\TrialOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        default => 'gray',
                    }),
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
            ->recordActions([
                Action::make('approveStock')
                    ->label('Approve Stock')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending' && in_array(auth()->user()->role, ['supervisor', 'sales', 'admin']))
                    ->form([
                        Select::make('stockist_id')
                            ->label('Deduct from Stockist')
                            ->options(function ($record) {
                                $agent = $record->agent;
                                $agentCities = is_array($agent->assigned_cities) ? $agent->assigned_cities : [];
                                $agentState = self::getCityStateMapping()[$agentCities[0] ?? ''] ?? null;

                                if (! $agentState) {
                                    return [];
                                }

                                return Stockist::where('state', $agentState)
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        self::approveTrialOrder($record, $data);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Stock Requisition')
                    ->modalDescription('Deduct stock from selected stockist.')
                    ->modalButton('Approve'),
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
        $record->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
        ]);

        $agent = $record->agent;
        $products = $record->products ?? [];

        if ($agent && ! empty($products)) {
            $agent->increment('stock_balance', $record->total_value);

            $stockistId = $data['stockist_id'] ?? null;
            $stockist = $stockistId ? Stockist::find($stockistId) : null;

            if ($stockist) {
                $totalDeducted = 0;

                foreach ($products as $product) {
                    $productName = $product['product_name'] ?? null;
                    $grammage = $product['grammage'] ?? null;
                    $quantity = $product['quantity'] ?? 0;
                    $price = $product['price'] ?? 0;
                    $lineTotal = $quantity * $price;

                    if ($productName && $grammage && $quantity > 0) {
                        $stockistStock = StockistStock::where('stockist_id', $stockist->id)
                            ->where('product_name', $productName)
                            ->where('grammage', $grammage)
                            ->first();

                        if ($stockistStock && $stockistStock->quantity >= $quantity) {
                            $stockistStock->decrement('quantity', $quantity);
                            $totalDeducted += $lineTotal;

                            StockistTransaction::create([
                                'stockist_id' => $stockist->id,
                                'user_id' => auth()->id(),
                                'field_agent_id' => $agent->id,
                                'trial_order_id' => $record->id,
                                'type' => 'deducted',
                                'amount' => $lineTotal,
                                'description' => "Deducted {$quantity}x {$productName} ({$grammage}g) for trial order",
                                'transaction_date' => now()->toDateString(),
                            ]);
                        }
                    }
                }

                if ($totalDeducted > 0) {
                    $stockist->decrement('stock_balance', $totalDeducted);
                }
            }
        }
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
