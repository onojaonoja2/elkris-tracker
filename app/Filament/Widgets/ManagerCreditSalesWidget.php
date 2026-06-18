<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use App\Models\State;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class ManagerCreditSalesWidget extends TableWidget
{
    protected static ?string $heading = 'Credit Sales by State';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['manager', 'admin']);
    }

    public function table(Table $table): Table
    {
        $preset = Session::get('manager_date_preset', 'today');
        $dateRange = self::getDateRange($preset);

        $agentIds = User::whereNotNull('state_id')
            ->whereIn('role', ['community_sales_representative', 'open_market', 'retail_market'])
            ->pluck('id');

        $creditData = SalesRecord::whereIn('agent_id', $agentIds)
            ->where('is_credit', true)
            ->where('status', 'approved')
            ->when($dateRange, fn ($q, $range) => $q->whereBetween('created_at', $range))
            ->with('agent.state')
            ->get()
            ->groupBy(fn ($r) => $r->agent->state_id ?? 'unknown')
            ->map(function ($records, $stateId) {
                $state = State::find($stateId);
                $pending = $records->where('credit_status', 'pending_payment');
                $collected = $records->where('credit_status', 'collected');
                $overdue = $pending->filter(fn ($r) => $r->expected_collection_date && $r->expected_collection_date->isPast());

                return [
                    'state_name' => $state?->name ?? 'Unknown',
                    'total_credit_value' => $records->sum('total_value'),
                    'pending_count' => $pending->count(),
                    'pending_value' => $pending->sum('total_value'),
                    'collected_count' => $collected->count(),
                    'collected_value' => $collected->sum('total_value'),
                    'overdue_count' => $overdue->count(),
                    'overdue_value' => $overdue->sum('total_value'),
                ];
            })
            ->values();

        return $table
            ->query(fn () => State::whereRaw('1=0'))
            ->columns([
                TextColumn::make('state_name')
                    ->label('State')
                    ->searchable(),
                TextColumn::make('total_credit_value')
                    ->label('Total Credit (₦)')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('pending_count')
                    ->label('Pending')
                    ->sortable(),
                TextColumn::make('pending_value')
                    ->label('Pending Value (₦)')
                    ->money('NGN'),
                TextColumn::make('collected_count')
                    ->label('Collected')
                    ->sortable(),
                TextColumn::make('collected_value')
                    ->label('Collected Value (₦)')
                    ->money('NGN'),
                TextColumn::make('overdue_count')
                    ->label('Overdue')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('overdue_value')
                    ->label('Overdue Value (₦)')
                    ->money('NGN'),
            ])
            ->record(fn () => $creditData->toArray())
            ->defaultSort('total_credit_value', 'desc')
            ->paginated(false);
    }

    private static function getDateRange(?string $preset): ?array
    {
        return match ($preset) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => null,
        };
    }
}
