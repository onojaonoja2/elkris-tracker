<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ManagerPortfolioPerAgentWidget extends TableWidget
{
    protected static ?string $heading = 'Portfolio per Team Lead';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        $leads = User::where('role', 'lead')->with('reps')->get();

        return $table
            ->query(fn (): Builder => User::query()->where('role', 'lead'))
            ->columns([
                TextColumn::make('name')
                    ->label('Team Lead')
                    ->searchable(),
                TextColumn::make('rep_count')
                    ->label('Number of Reps')
                    ->getStateUsing(fn (User $record): int => $record->reps()->count())
                    ->numeric(),
                TextColumn::make('customer_count')
                    ->label('Total Customers')
                    ->getStateUsing(function (User $record): int {
                        $repIds = $record->reps()->pluck('id');

                        return Customer::whereIn('rep_id', $repIds)
                            ->where('rep_acceptance_status', 'accepted')
                            ->count();
                    })
                    ->numeric(),
            ]);
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'manager' || auth()->user()->role === 'admin';
    }
}
