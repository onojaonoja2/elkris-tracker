<?php

namespace App\Filament\Widgets;

use App\Models\StockistStock;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class StockistStocksWidget extends TableWidget
{
    protected static ?string $heading = 'My Stock';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->role === 'stockist';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => auth()->user()->stockist?->stocks()->getQuery() ?? StockistStock::whereRaw('0 = 1'))
            ->columns([
                TextColumn::make('product_name')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('grammage')
                    ->label('Grammage (g)')
                    ->suffix('g'),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric(),
            ])
            ->paginated(false);
    }
}
