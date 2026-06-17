<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Enums\TrialOrderStatus;
use App\Models\TrialOrder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class StockistTrialOrdersWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->role === 'stockist';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $user = auth()->user();

                return TrialOrder::where('stockist_id', $user->stockist_id)
                    ->orderBy('created_at', 'desc');
            })
            ->columns([
                TextColumn::make('id')
                    ->label('Order #'),
                TextColumn::make('products')
                    ->label('Products')
                    ->formatStateUsing(fn ($products) => collect($products)
                        ->map(fn ($p) => "{$p['quantity']}x {$p['product_name']} ({$p['grammage']}g)")
                        ->implode(', '))
                    ->limit(50),
                TextColumn::make('total_value')
                    ->label('Total (₦)')
                    ->money('NGN'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (TrialOrderStatus $state): string => $state->color())
                    ->formatStateUsing(fn (TrialOrderStatus $state): string => $state->getLabel()),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (PaymentStatus $state): string => $state->color())
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->getLabel()),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
