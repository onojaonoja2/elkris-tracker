<?php

namespace App\Filament\Widgets;

use App\Models\State;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
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
        return auth()->user()->role === 'manager' || auth()->user()->role === 'admin';
    }

    public function table(Table $table): Table
    {
        $stateIds = State::pluck('id', 'name');

        $userRoles = ['field_agent', 'community_sales_representative', 'open_market', 'retail_market'];
        $agentCounts = User::select(DB::raw('s.id as state_id'), DB::raw('COUNT(*) as count'))
            ->join('lgas', 'users.lga_id', '=', 'lgas.id')
            ->join('states as s', 'lgas.state_id', '=', 's.id')
            ->whereIn('role', $userRoles)
            ->groupBy('s.id')
            ->pluck('count', 'state_id');

        $leadCounts = User::select(DB::raw('s.id as state_id'), DB::raw('COUNT(*) as count'))
            ->join('lgas', 'users.lga_id', '=', 'lgas.id')
            ->join('states as s', 'lgas.state_id', '=', 's.id')
            ->where('role', 'lead')
            ->groupBy('s.id')
            ->pluck('count', 'state_id');

        $repCounts = User::select(DB::raw('s.id as state_id'), DB::raw('COUNT(*) as count'))
            ->join('lgas', 'users.lga_id', '=', 'lgas.id')
            ->join('states as s', 'lgas.state_id', '=', 's.id')
            ->where('role', 'rep')
            ->groupBy('s.id')
            ->pluck('count', 'state_id');

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
            ->paginated(false);
    }
}
