<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class SalesCsrOverviewWidget extends TableWidget
{
    protected static ?int $sort = 6;

    protected static ?string $heading = 'CSR Overview';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        $csrIds = User::where('role', 'community_sales_representative')
            ->pluck('id');

        $stockCounts = AgentStock::whereIn('user_id', $csrIds)
            ->selectRaw('user_id, SUM(quantity) as total_qty')
            ->groupBy('user_id')
            ->pluck('total_qty', 'user_id');

        $stockDetails = AgentStock::whereIn('user_id', $csrIds)
            ->where('quantity', '>', 0)
            ->selectRaw('user_id, product_name, grammage, quantity')
            ->get()
            ->groupBy('user_id');

        return $table
            ->query(
                fn () => User::where('role', 'community_sales_representative')
                    ->with(['state', 'lga'])
                    ->orderBy('name')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('CSR Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->placeholder('N/A'),

                TextColumn::make('lga.name')
                    ->label('LGA')
                    ->searchable(),

                TextColumn::make('state.name')
                    ->label('State')
                    ->searchable(),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Suspended'),

                TextColumn::make('stock_units')
                    ->label('Stock Units')
                    ->getStateUsing(fn (User $record): int => $stockCounts->get($record->id, 0))
                    ->sortable(),

                TextColumn::make('stock_breakdown')
                    ->label('Stock Details')
                    ->getStateUsing(function (User $record) use ($stockDetails): string {
                        $details = $stockDetails->get($record->id);
                        if (! $details || $details->isEmpty()) {
                            return 'No stock';
                        }

                        return $details->map(fn ($s) => "{$s->product_name} ({$s->grammage}g): {$s->quantity}")
                            ->implode(', ');
                    })
                    ->limit(60),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50, -1]);
    }
}
