<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use App\Models\State;
use App\Support\DashboardDateScope;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class ManagerCreditSalesWidget extends TableWidget
{
    protected static ?string $heading = 'Credit Sales by State';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }

    private function todaySql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "DATE('now')" : 'CURDATE()';
    }

    private function creditAggregates(): Collection
    {
        [$from, $to] = DashboardDateScope::fromSession();
        $todaySql = $this->todaySql();

        return SalesRecord::select(
            DB::raw('lga_state.name as state_name'),
            DB::raw('COALESCE(SUM(total_value), 0) as total_credit_value'),
            DB::raw("SUM(CASE WHEN credit_status IN ('pending_payment', 'partially_collected') THEN 1 ELSE 0 END) as pending_count"),
            DB::raw("SUM(CASE WHEN credit_status IN ('pending_payment', 'partially_collected') THEN total_value ELSE 0 END) as pending_value"),
            DB::raw("SUM(CASE WHEN credit_status = 'collected' THEN 1 ELSE 0 END) as collected_count"),
            DB::raw("SUM(CASE WHEN credit_status = 'collected' THEN total_value ELSE 0 END) as collected_value"),
            DB::raw("SUM(CASE WHEN credit_status IN ('pending_payment', 'partially_collected') AND expected_collection_date < {$todaySql} THEN 1 ELSE 0 END) as overdue_count"),
            DB::raw("SUM(CASE WHEN credit_status IN ('pending_payment', 'partially_collected') AND expected_collection_date < {$todaySql} THEN total_value ELSE 0 END) as overdue_value"),
        )
            ->leftJoin('users', 'sales_records.agent_id', '=', 'users.id')
            ->leftJoin('lgas', 'users.lga_id', '=', 'lgas.id')
            ->leftJoin('states as lga_state', 'lgas.state_id', '=', 'lga_state.id')
            ->where('is_credit', true)
            ->where('status', 'approved')
            ->whereBetween('sales_records.created_at', [$from, $to])
            ->groupBy('lga_state.name')
            ->orderByDesc('total_credit_value')
            ->get()
            ->keyBy('state_name');
    }

    public function table(Table $table): Table
    {
        $aggregates = $this->creditAggregates();

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
            ->recordActions([
                Action::make('viewStateBreakdown')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (State $record): string => "Credit Sales in {$record->name}")
                    ->modalContent(fn (State $record) => view('filament.state-breakdown-modal', [
                        'entity' => 'credit',
                        'stateId' => $record->id,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('5xl'),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $aggregates = $this->creditAggregates();
                        $records = $this->getFilteredTableQuery()->orderBy('name')->get();

                        return response()->streamDownload(function () use ($records, $aggregates) {
                            $file = fopen('php://output', 'w');
                            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                            fputcsv($file, ['State', 'Total Credit (₦)', 'Pending Count', 'Pending Value (₦)', 'Collected Count', 'Collected Value (₦)', 'Overdue Count', 'Overdue Value (₦)']);

                            foreach ($records as $record) {
                                $agg = $aggregates->get($record->name);

                                fputcsv($file, [
                                    $record->name,
                                    number_format($agg?->total_credit_value ?? 0, 2),
                                    $agg?->pending_count ?? 0,
                                    number_format($agg?->pending_value ?? 0, 2),
                                    $agg?->collected_count ?? 0,
                                    number_format($agg?->collected_value ?? 0, 2),
                                    $agg?->overdue_count ?? 0,
                                    number_format($agg?->overdue_value ?? 0, 2),
                                ]);
                            }

                            fclose($file);
                        }, 'credit_sales_by_state_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
