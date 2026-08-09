<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class CsrSalesRecordsWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'My Sales Records';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->query(SalesRecord::where('agent_id', auth()->id()))
            ->columns([
                TextColumn::make('id')
                    ->label('Record #'),
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
