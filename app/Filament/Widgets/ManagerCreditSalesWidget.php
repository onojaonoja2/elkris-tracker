<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use App\Models\State;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
        return in_array(auth()->user()->role, ['admin', 'manager', 'general_manager']);
    }

    public function table(Table $table): Table
    {
        $preset = Session::get('manager_date_preset', 'today');
        $dateRange = self::getDateRange($preset);

        $aggregates = SalesRecord::select(
            DB::raw('lga_state.name as state_name'),
            DB::raw('COALESCE(SUM(total_value), 0) as total_credit_value'),
            DB::raw("SUM(CASE WHEN credit_status = 'pending_payment' THEN 1 ELSE 0 END) as pending_count"),
            DB::raw("SUM(CASE WHEN credit_status = 'pending_payment' THEN total_value ELSE 0 END) as pending_value"),
            DB::raw("SUM(CASE WHEN credit_status = 'collected' THEN 1 ELSE 0 END) as collected_count"),
            DB::raw("SUM(CASE WHEN credit_status = 'collected' THEN total_value ELSE 0 END) as collected_value"),
            DB::raw("SUM(CASE WHEN credit_status = 'pending_payment' AND expected_collection_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count"),
            DB::raw("SUM(CASE WHEN credit_status = 'pending_payment' AND expected_collection_date < CURDATE() THEN total_value ELSE 0 END) as overdue_value"),
        )
            ->leftJoin('users', 'sales_records.agent_id', '=', 'users.id')
            ->leftJoin('lgas', 'users.lga_id', '=', 'lgas.id')
            ->leftJoin('states as lga_state', 'lgas.state_id', '=', 'lga_state.id')
            ->where('is_credit', true)
            ->where('status', 'approved')
            ->when($dateRange, fn ($q, $range) => $q->whereBetween('sales_records.created_at', $range))
            ->groupBy('lga_state.name')
            ->orderByDesc('total_credit_value')
            ->get()
            ->keyBy('state_name');

        return $table
            ->query(fn (): Builder => State::query()->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->label('State')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_credit_value')
                    ->label('Total Credit (₦)')
                    ->getStateUsing(fn ($record): float => $aggregates->get($record->name)?->total_credit_value ?? 0)
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('pending_count')
                    ->label('Pending')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->name)?->pending_count ?? 0)
                    ->numeric()
                    ->sortable(),
                TextColumn::make('pending_value')
                    ->label('Pending Value (₦)')
                    ->getStateUsing(fn ($record): float => $aggregates->get($record->name)?->pending_value ?? 0)
                    ->money('NGN'),
                TextColumn::make('collected_count')
                    ->label('Collected')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->name)?->collected_count ?? 0)
                    ->numeric()
                    ->sortable(),
                TextColumn::make('collected_value')
                    ->label('Collected Value (₦)')
                    ->getStateUsing(fn ($record): float => $aggregates->get($record->name)?->collected_value ?? 0)
                    ->money('NGN'),
                TextColumn::make('overdue_count')
                    ->label('Overdue')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->name)?->overdue_count ?? 0)
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('overdue_value')
                    ->label('Overdue Value (₦)')
                    ->getStateUsing(fn ($record): float => $aggregates->get($record->name)?->overdue_value ?? 0)
                    ->money('NGN'),
            ])
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
