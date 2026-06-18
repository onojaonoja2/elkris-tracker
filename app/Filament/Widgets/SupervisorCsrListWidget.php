<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\SalesRecord;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class SupervisorCsrListWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'CSR Overview';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        $from = Session::get('supervisor_date_from', now()->startOfDay()->toDateTimeString());
        $to = Session::get('supervisor_date_to', now()->endOfDay()->toDateTimeString());

        $csrIds = User::where('role', 'community_sales_representative')->pluck('id');

        $stockCounts = AgentStock::whereIn('user_id', $csrIds)
            ->selectRaw('user_id, SUM(quantity) as total_qty')
            ->groupBy('user_id')
            ->pluck('total_qty', 'user_id');

        $salesCounts = SalesRecord::whereIn('agent_id', $csrIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('agent_id, COUNT(*) as count, COALESCE(SUM(total_value), 0) as total_value')
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');

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

                TextColumn::make('lga.name')
                    ->label('LGA')
                    ->searchable(),

                TextColumn::make('state.name')
                    ->label('State')
                    ->searchable(),

                TextColumn::make('stock_units')
                    ->label('Stock Units')
                    ->getStateUsing(fn (User $record): int => $stockCounts->get($record->id, 0))
                    ->sortable(),

                TextColumn::make('sales_count')
                    ->label('Sales Count')
                    ->getStateUsing(fn (User $record): int => $salesCounts->get($record->id)?->count ?? 0)
                    ->sortable(),

                TextColumn::make('sales_value')
                    ->label('Sales Value')
                    ->getStateUsing(fn (User $record): string => '₦'.number_format($salesCounts->get($record->id)?->total_value ?? 0, 2))
                    ->money('NGN')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50, -1]);
    }
}
