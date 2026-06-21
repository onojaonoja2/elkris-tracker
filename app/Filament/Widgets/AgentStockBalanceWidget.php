<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class AgentStockBalanceWidget extends TableWidget
{
    protected static ?string $heading = 'My Stock Balance';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user() && in_array(auth()->user()->role, ['community_sales_representative', 'open_market', 'retail_market']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AgentStock::where('user_id', auth()->id())
                    ->orderBy('quantity', 'desc')
            )
            ->columns([
                TextColumn::make('product_name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('grammage')
                    ->label('Weight (g)')
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Qty Available')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
            ])
            ->defaultSort('quantity', 'desc')
            ->paginated(false);
    }
}
