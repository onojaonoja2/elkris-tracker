<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasBreakdownViewAction;
use App\Models\SalesRecord;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class SupervisorCreditSalesWidget extends TableWidget
{
    use HasBreakdownViewAction;

    protected static ?string $heading = 'Credit Sales by CSR';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasRole('supervisor');
    }

    private function todaySql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "DATE('now')" : 'CURDATE()';
    }

    public function table(Table $table): Table
    {
        $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');

        $todaySql = $this->todaySql();

        $aggregates = SalesRecord::select(
            'agent_id',
            DB::raw('COALESCE(SUM(total_value), 0) as total_credit_value'),
            DB::raw("SUM(CASE WHEN credit_status IN ('pending_payment', 'partially_collected') THEN 1 ELSE 0 END) as pending_count"),
            DB::raw("SUM(CASE WHEN credit_status IN ('pending_payment', 'partially_collected') THEN total_value ELSE 0 END) as pending_value"),
            DB::raw("SUM(CASE WHEN credit_status = 'collected' THEN 1 ELSE 0 END) as collected_count"),
            DB::raw("SUM(CASE WHEN credit_status = 'collected' THEN total_value ELSE 0 END) as collected_value"),
            DB::raw("SUM(CASE WHEN credit_status IN ('pending_payment', 'partially_collected') AND expected_collection_date < {$todaySql} THEN 1 ELSE 0 END) as overdue_count"),
            DB::raw("SUM(CASE WHEN credit_status IN ('pending_payment', 'partially_collected') AND expected_collection_date < {$todaySql} THEN total_value ELSE 0 END) as overdue_value"),
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
            ->recordActions([
                $this->breakdownViewAction(),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');

                        $todaySql = $this->todaySql();

                        $aggregates = SalesRecord::select(
                            'agent_id',
                            DB::raw('COALESCE(SUM(total_value), 0) as total_credit_value'),
                            DB::raw("SUM(CASE WHEN credit_status IN ('pending_payment', 'partially_collected') THEN 1 ELSE 0 END) as pending_count"),
                            DB::raw("SUM(CASE WHEN credit_status IN ('pending_payment', 'partially_collected') THEN total_value ELSE 0 END) as pending_value"),
                            DB::raw("SUM(CASE WHEN credit_status = 'collected' THEN 1 ELSE 0 END) as collected_count"),
                            DB::raw("SUM(CASE WHEN credit_status = 'collected' THEN total_value ELSE 0 END) as collected_value"),
                            DB::raw("SUM(CASE WHEN credit_status IN ('pending_payment', 'partially_collected') AND expected_collection_date < {$todaySql} THEN 1 ELSE 0 END) as overdue_count"),
                            DB::raw("SUM(CASE WHEN credit_status IN ('pending_payment', 'partially_collected') AND expected_collection_date < {$todaySql} THEN total_value ELSE 0 END) as overdue_value"),
                        )
                            ->whereIn('agent_id', $csrIds)
                            ->where('is_credit', true)
                            ->where('status', 'approved')
                            ->groupBy('agent_id')
                            ->get()
                            ->keyBy('agent_id');

                        $records = $this->getFilteredQuery()->get();

                        return response()->streamDownload(function () use ($records, $aggregates) {
                            $file = fopen('php://output', 'w');
                            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                            fputcsv($file, ['CSR', 'Total Credit (₦)', 'Pending Count', 'Pending Value (₦)', 'Collected Count', 'Collected Value (₦)', 'Overdue Count', 'Overdue Value (₦)']);

                            foreach ($records as $record) {
                                $agg = $aggregates->get($record->id);

                                fputcsv($file, [
                                    $record->name,
                                    number_format($agg?->total_credit_value ?? 0, 2),
                                    number_format($agg?->pending_count ?? 0, 0),
                                    number_format($agg?->pending_value ?? 0, 2),
                                    number_format($agg?->collected_count ?? 0, 0),
                                    number_format($agg?->collected_value ?? 0, 2),
                                    number_format($agg?->overdue_count ?? 0, 0),
                                    number_format($agg?->overdue_value ?? 0, 2),
                                ]);
                            }

                            fclose($file);
                        }, 'credit_sales_by_csr_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->paginated([10, 25, 50]);
    }
}
