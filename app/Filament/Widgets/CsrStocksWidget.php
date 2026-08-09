<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class CsrStocksWidget extends TableWidget
{
    protected static ?int $sort = 1;

    protected static ?string $heading = 'My Stock';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->query(AgentStock::where('user_id', auth()->id()))
            ->columns([
                TextColumn::make('product_name')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('grammage')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state.'g'),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->sortable(),
            ])
            ->defaultSort('product_name');
    }
}
