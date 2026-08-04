<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\SalesRecord;
use App\Models\User;
use App\Support\DashboardDateScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class CsrOverviewWidget extends TableWidget
{
    protected static ?string $heading = 'CSR Overview';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['rep', 'lead']);
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();

        $pairedCsrIds = $this->getPairedCsrIds($user);
        [$from, $to] = DashboardDateScope::fromSession();

        $stockCounts = AgentStock::whereIn('user_id', $pairedCsrIds)
            ->selectRaw('user_id, SUM(quantity) as total_qty')
            ->groupBy('user_id')
            ->pluck('total_qty', 'user_id');

        $salesCounts = SalesRecord::whereIn('agent_id', $pairedCsrIds)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('agent_id, COUNT(*) as count, COALESCE(SUM(total_value), 0) as total_value')
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');

        $revenueByAgent = SalesRecord::revenueByAgent($pairedCsrIds->all(), $from, $to);

        $isAttached = fn (int $csrId): bool => $pairedCsrIds->contains($csrId);

        $pairedCsrIdsList = $pairedCsrIds->toArray();
        $pairedList = ! empty($pairedCsrIdsList) ? implode(',', $pairedCsrIdsList) : '0';

        return $table
            ->query(fn () => User::where('role', 'community_sales_representative')
                ->with(['state', 'lga'])
                ->orderByRaw("FIELD(id, {$pairedList}) DESC")
                ->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->label('CSR Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone_number')
                    ->label('Contact')
                    ->searchable(),

                TextColumn::make('lga.name')
                    ->label('LGA')
                    ->searchable(),

                TextColumn::make('state.name')
                    ->label('State')
                    ->searchable(),

                TextColumn::make('stock_units')
                    ->label('Stock Units')
                    ->getStateUsing(fn (User $record) => $isAttached($record->id)
                        ? number_format($stockCounts->get($record->id, 0))
                        : '-')
                    ->sortable(),

                TextColumn::make('sales_count')
                    ->label('Sales Count')
                    ->getStateUsing(fn (User $record) => $isAttached($record->id)
                        ? ($salesCounts->get($record->id)?->count ?? 0)
                        : '-')
                    ->sortable(),

                TextColumn::make('sales_value')
                    ->label('Sales Value')
                    ->getStateUsing(fn (User $record) => $isAttached($record->id)
                        ? '₦'.number_format($revenueByAgent->get($record->id)?->revenue ?? 0, 2)
                        : '-')
                    ->sortable(),

                TextColumn::make('attachment_status')
                    ->label('Status')
                    ->getStateUsing(fn (User $record) => $isAttached($record->id) ? 'Attached' : 'Not Attached')
                    ->badge()
                    ->colors([
                        'success' => 'Attached',
                        'gray' => 'Not Attached',
                    ]),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50, -1]);
    }

    private function getPairedCsrIds(User $user): Collection
    {
        if ($user->hasRole('rep')) {
            return User::where('portfolio_agent_id', $user->id)
                ->where('role', 'community_sales_representative')
                ->pluck('id');
        }

        if ($user->hasRole('lead')) {
            $repIds = User::where('lead_id', $user->id)->where('role', 'rep')->pluck('id')->toArray();

            return User::where(function ($query) use ($repIds, $user) {
                $query->whereIn('portfolio_agent_id', $repIds)
                    ->orWhere('portfolio_agent_id', $user->id);
            })
                ->where('role', 'community_sales_representative')
                ->pluck('id');
        }

        return collect();
    }
}
