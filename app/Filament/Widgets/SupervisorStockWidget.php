<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class SupervisorStockWidget extends TableWidget
{
    protected static ?int $sort = 1;

    protected static ?string $heading = 'CSR Stock Levels';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        $agentIds = User::where('role', 'community_sales_representative')->active()->pluck('id');

        return $table
            ->query(
                fn () => AgentStock::whereIn('user_id', $agentIds)
                    ->with('agent')
                    ->orderBy('product_name')
                    ->orderBy('grammage')
            )
            ->columns([
                TextColumn::make('agent.name')
                    ->label('Agent')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product_name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('grammage')
                    ->label('Grammage')
                    ->formatStateUsing(fn ($state) => "{$state}g")
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
            ])
            ->defaultSort('product_name');
    }
}
