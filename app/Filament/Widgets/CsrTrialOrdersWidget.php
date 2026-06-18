<?php

namespace App\Filament\Widgets;

use App\Models\TrialOrder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CsrTrialOrdersWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'My Trial Orders';

    public function table(Table $table): Table
    {
        return $table
            ->query(TrialOrder::where('agent_id', auth()->id()))
            ->columns([
                TextColumn::make('id')
                    ->label('Order #'),
                TextColumn::make('total_value')
                    ->label('Value')
                    ->money('NGN'),
                TextColumn::make('products')
                    ->label('Products')
                    ->formatStateUsing(fn ($products) => collect($products)->map(fn ($p) => "{$p['quantity']}x {$p['product_name']}")->implode(', '))
                    ->limit(40),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
