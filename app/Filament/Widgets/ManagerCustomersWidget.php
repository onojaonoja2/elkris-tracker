<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\BadgeColumn;
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
        return auth()->user()->role === 'manager' || auth()->user()->role === 'admin';
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
                BadgeColumn::make('rep_acceptance_status')
                    ->label('Status')
                    ->colors([
                        'success' => 'accepted',
                        'warning' => 'pending',
                        'danger' => 'rejected',
                    ]),
                TextColumn::make('created_at')
                    ->label('Date Added')
                    ->date('d/m/Y'),
            ])
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
                Action::make('export')
                    ->label('Export Customers')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () use ($from, $to) {
                        $query = Customer::query()
                            ->whereDate('created_at', '>=', $from)
                            ->whereDate('created_at', '<=', $to)
                            ->with(['rep', 'lead']);

                        $customers = $query->orderBy('created_at', 'desc')->get();
                        $data = [];
                        foreach ($customers as $customer) {
                            $data[] = [
                                $customer->customer_name,
                                $customer->phone_number,
                                $customer->address,
                                $customer->city,
                                $customer->state,
                                $customer->region,
                                $customer->rep?->name ?? 'N/A',
                                $customer->lead?->name ?? 'N/A',
                                $customer->created_at->format('d/m/Y'),
                            ];
                        }

                        return response()->streamDownload(function () use ($data) {
                            $file = fopen('php://output', 'w');
                            fputcsv($file, ['Customer Name', 'Phone', 'Address', 'City', 'State', 'Region', 'Rep', 'Team Lead', 'Date Added']);
                            foreach ($data as $row) {
                                fputcsv($file, $row);
                            }
                            fclose($file);
                        }, 'customers_export_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment',
                        ]);
                    }),
            ])
            ->paginated(20);
    }
}
