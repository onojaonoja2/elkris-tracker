<?php

namespace App\Filament\Widgets;

use App\Filament\Exports\CustomerExporter;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class ManagerCustomersWidget extends TableWidget
{
    protected static ?string $heading = 'System Customers';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }

    protected function getDefaultDateRange(): array
    {
        $now = Carbon::now('Africa/Lagos');

        $preset = Session::get('manager_customer_date_preset', 'today');

        match ($preset) {
            'yesterday' => $from = $now->copy()->subDay()->startOfDay(),
            'this_week' => $from = $now->copy()->startOfWeek(),
            'this_month' => $from = $now->copy()->startOfMonth(),
            'lifetime' => $from = Carbon::now('Africa/Lagos')->subYears(10),
            default => $from = $now->copy()->setHour(8)->setMinute(0)->setSecond(0),
        };

        if ($preset !== 'lifetime') {
            if ($preset === 'yesterday') {
                $to = $now->copy()->subDay()->endOfDay();
            } elseif ($preset === 'this_week') {
                $to = $now->copy()->endOfWeek();
            } elseif ($preset === 'this_month') {
                $to = $now->copy()->endOfMonth();
            } else {
                $to = $now;
            }
        } else {
            $to = Carbon::now('Africa/Lagos');
        }

        return ['from' => $from, 'to' => $to];
    }

    public function table(Table $table): Table
    {
        $defaultRange = $this->getDefaultDateRange();
        $from = $defaultRange['from'];
        $to = $defaultRange['to'];

        $leadIds = User::where('role', 'lead')->pluck('id')->toArray();
        $repIds = User::whereIn('lead_id', $leadIds)->where('role', 'rep')->pluck('id')->toArray();

        return $table
            ->query(fn (): Builder => Customer::query()
                ->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to)
                ->with(['rep', 'lead']))
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Customer Name')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->searchable(),
                TextColumn::make('rep.name')
                    ->label('Rep')
                    ->searchable(),
                TextColumn::make('lead.name')
                    ->label('Team Lead')
                    ->searchable(),
                TextColumn::make('city')
                    ->label('City')
                    ->searchable(),
                TextColumn::make('state')
                    ->label('State')
                    ->searchable(),
                TextColumn::make('region')
                    ->label('Region')
                    ->searchable(),
                TextColumn::make('rep_acceptance_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'accepted' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Date Added')
                    ->date('d/m/Y'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('rep_id')
                    ->label('Filter by Rep')
                    ->options(fn () => User::whereIn('id', $repIds)->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn ($q) => $q->where('rep_id', $data['value']))),
                SelectFilter::make('lead_id')
                    ->label('Filter by Team Lead')
                    ->options(fn () => User::whereIn('id', $leadIds)->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn ($q) => $q->where('lead_id', $data['value']))),
                SelectFilter::make('city')
                    ->label('Filter by City')
                    ->options(fn () => Customer::whereNotNull('city')->distinct()->pluck('city', 'city')->toArray())
                    ->searchable(),
                SelectFilter::make('state')
                    ->label('Filter by State')
                    ->options(fn () => Customer::whereNotNull('state')->distinct()->pluck('state', 'state')->toArray()),
                SelectFilter::make('region')
                    ->label('Filter by Region')
                    ->options(fn () => Customer::whereNotNull('region')->distinct()->pluck('region', 'region')->toArray()),
                Filter::make('date_range')
                    ->label('Date Range')
                    ->form([
                        Select::make('preset')
                            ->options([
                                'today' => 'Today (8AM-5PM)',
                                'yesterday' => 'Yesterday',
                                'this_week' => 'This Week',
                                'this_month' => 'This Month',
                                'lifetime' => 'Lifetime',
                            ])
                            ->default('today')
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query;
                    }),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(CustomerExporter::class),
            ])
            ->paginated(20);
    }
}
