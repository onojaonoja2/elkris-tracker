<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use App\Models\State;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class ManagerSalesRecordsByStateWidget extends TableWidget
{
    protected static ?string $heading = 'Sales Records by State';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }

    /**
     * @return array{0: Collection, 1: Collection}
     */
    private function salesAggregates(): array
    {
        $aggregates = SalesRecord::select(
            DB::raw('lga_state.name as state_name'),
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN status IN ('pending', 'receipt_uploaded') THEN 1 ELSE 0 END) as pending"),
            DB::raw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved"),
            DB::raw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected"),
        )
            ->leftJoin('users', 'sales_records.agent_id', '=', 'users.id')
            ->leftJoin('lgas', 'users.lga_id', '=', 'lgas.id')
            ->leftJoin('states as lga_state', 'lgas.state_id', '=', 'lga_state.id')
            ->groupBy('lga_state.name')
            ->orderBy('state_name')
            ->get()
            ->keyBy('state_name');

        return [$aggregates, SalesRecord::revenueByState()];
    }

    public function table(Table $table): Table
    {
        [$aggregates, $revenueByState] = $this->salesAggregates();

        return $table
            ->query(fn (): Builder => State::query()->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->label('State')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->name)?->total ?? 0)
                    ->numeric()
                    ->sortable(),
                TextColumn::make('pending')
                    ->label('Pending')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->name)?->pending ?? 0)
                    ->numeric()
                    ->color('warning'),
                TextColumn::make('approved')
                    ->label('Approved')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->name)?->approved ?? 0)
                    ->numeric()
                    ->color('success'),
                TextColumn::make('rejected')
                    ->label('Rejected')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->name)?->rejected ?? 0)
                    ->numeric()
                    ->color('danger'),
                TextColumn::make('total_value')
                    ->label('Total Value (₦)')
                    ->getStateUsing(fn ($record): float => $revenueByState->get($record->name)?->revenue ?? 0)
                    ->money('NGN')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('viewStateBreakdown')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (State $record): string => "Sales Records in {$record->name}")
                    ->modalContent(fn (State $record) => view('filament.state-breakdown-modal', [
                        'entity' => 'sales',
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
                        [$aggregates, $revenueByState] = $this->salesAggregates();
                        $records = $this->getFilteredTableQuery()->orderBy('name')->get();

                        return response()->streamDownload(function () use ($records, $aggregates, $revenueByState) {
                            $file = fopen('php://output', 'w');
                            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                            fputcsv($file, ['State', 'Total', 'Pending', 'Approved', 'Rejected', 'Total Value (₦)']);

                            foreach ($records as $record) {
                                $agg = $aggregates->get($record->name);

                                fputcsv($file, [
                                    $record->name,
                                    $agg?->total ?? 0,
                                    $agg?->pending ?? 0,
                                    $agg?->approved ?? 0,
                                    $agg?->rejected ?? 0,
                                    number_format($revenueByState->get($record->name)?->revenue ?? 0, 2),
                                ]);
                            }

                            fclose($file);
                        }, 'sales_records_by_state_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
