<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class SupervisorCreditSalesWidget extends TableWidget
{
    protected static ?string $heading = 'Credit Sales by CSR';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasRole('supervisor');
    }

    public function table(Table $table): Table
    {
        $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');

        $aggregates = SalesRecord::select(
            'agent_id',
            DB::raw('COALESCE(SUM(total_value), 0) as total_credit_value'),
            DB::raw("SUM(CASE WHEN credit_status = 'pending_payment' THEN 1 ELSE 0 END) as pending_count"),
            DB::raw("SUM(CASE WHEN credit_status = 'pending_payment' THEN total_value ELSE 0 END) as pending_value"),
            DB::raw("SUM(CASE WHEN credit_status = 'collected' THEN 1 ELSE 0 END) as collected_count"),
            DB::raw("SUM(CASE WHEN credit_status = 'collected' THEN total_value ELSE 0 END) as collected_value"),
            DB::raw("SUM(CASE WHEN credit_status = 'pending_payment' AND expected_collection_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count"),
            DB::raw("SUM(CASE WHEN credit_status = 'pending_payment' AND expected_collection_date < CURDATE() THEN total_value ELSE 0 END) as overdue_value"),
        )
            ->whereIn('agent_id', $csrIds)
            ->where('is_credit', true)
            ->where('status', 'approved')
            ->groupBy('agent_id')
            ->orderByDesc('total_credit_value')
            ->get()
            ->keyBy('agent_id');

        return $table
            ->query(fn (): Builder => User::whereIn('id', $csrIds)->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->label('CSR')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_credit_value')
                    ->label('Total Credit (₦)')
                    ->getStateUsing(fn ($record): float => $aggregates->get($record->id)?->total_credit_value ?? 0)
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('pending_count')
                    ->label('Pending')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->id)?->pending_count ?? 0)
                    ->numeric()
                    ->sortable(),
                TextColumn::make('pending_value')
                    ->label('Pending Value (₦)')
                    ->getStateUsing(fn ($record): float => $aggregates->get($record->id)?->pending_value ?? 0)
                    ->money('NGN'),
                TextColumn::make('collected_count')
                    ->label('Collected')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->id)?->collected_count ?? 0)
                    ->numeric()
                    ->sortable(),
                TextColumn::make('collected_value')
                    ->label('Collected Value (₦)')
                    ->getStateUsing(fn ($record): float => $aggregates->get($record->id)?->collected_value ?? 0)
                    ->money('NGN'),
                TextColumn::make('overdue_count')
                    ->label('Overdue')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->id)?->overdue_count ?? 0)
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('overdue_value')
                    ->label('Overdue Value (₦)')
                    ->getStateUsing(fn ($record): float => $aggregates->get($record->id)?->overdue_value ?? 0)
                    ->money('NGN'),
            ])
            ->paginated(false);
    }
}
