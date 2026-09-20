<?php

namespace App\Filament\Widgets;

use App\Models\State;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class ManagerPeopleByStateWidget extends TableWidget
{
    protected static ?string $heading = 'People by State';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }

    /**
     * @return array{agents: Collection, leads: Collection, reps: Collection}
     */
    private function peopleAggregates(): array
    {
        $countsFor = fn (array|string $roles) => User::select(DB::raw('s.id as state_id'), DB::raw('COUNT(*) as count'))
            ->join('lgas', 'users.lga_id', '=', 'lgas.id')
            ->join('states as s', 'lgas.state_id', '=', 's.id')
            ->whereIn('role', (array) $roles)
            ->groupBy('s.id')
            ->pluck('count', 'state_id');

        return [
            'agents' => $countsFor(['field_agent', 'community_sales_representative', 'open_market', 'retail_market']),
            'leads' => $countsFor('lead'),
            'reps' => $countsFor('rep'),
        ];
    }

    public function table(Table $table): Table
    {
        $aggregates = $this->peopleAggregates();
        $agentCounts = $aggregates['agents'];
        $leadCounts = $aggregates['leads'];
        $repCounts = $aggregates['reps'];

        return $table
            ->query(fn (): Builder => State::query()->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->label('State')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('agent_count')
                    ->label('Agents')
                    ->getStateUsing(fn (State $record): int => $agentCounts->get($record->id, 0))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lead_count')
                    ->label('Leads')
                    ->getStateUsing(fn (State $record): int => $leadCounts->get($record->id, 0))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rep_count')
                    ->label('Reps')
                    ->getStateUsing(fn (State $record): int => $repCounts->get($record->id, 0))
                    ->numeric()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('viewStateBreakdown')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (State $record): string => "People in {$record->name}")
                    ->modalContent(fn (State $record) => view('filament.state-breakdown-modal', [
                        'entity' => 'people',
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
                        $aggregates = $this->peopleAggregates();
                        $records = $this->getFilteredTableQuery()->orderBy('name')->get();

                        return response()->streamDownload(function () use ($records, $aggregates) {
                            $file = fopen('php://output', 'w');
                            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                            fputcsv($file, ['State', 'Agents', 'Leads', 'Reps']);

                            foreach ($records as $record) {
                                fputcsv($file, [
                                    $record->name,
                                    $aggregates['agents']->get($record->id, 0),
                                    $aggregates['leads']->get($record->id, 0),
                                    $aggregates['reps']->get($record->id, 0),
                                ]);
                            }

                            fclose($file);
                        }, 'people_by_state_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
