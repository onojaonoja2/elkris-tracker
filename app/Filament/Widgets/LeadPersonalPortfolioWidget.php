<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class LeadPersonalPortfolioWidget extends TableWidget
{
    protected static ?string $heading = 'My Personal Portfolio';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->role === 'lead';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Customer::query()
                ->where('rep_id', auth()->id())
                ->where('rep_acceptance_status', 'accepted'))
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Customer Name')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Address')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('city')
                    ->label('City')
                    ->searchable(),
                TextColumn::make('total_purchases')
                    ->label('Purchases')
                    ->getStateUsing(fn ($record): int => $record->orders()->where('status', 'delivered')->count())
                    ->sortable()
                    ->color(fn ($state): string => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('last_called')
                    ->label('Last Called')
                    ->getStateUsing(fn ($record): string => $record->callLogs()->latest('called_at')->first()?->called_at?->diffForHumans() ?? 'Never')
                    ->color(fn ($record): string => $record->callLogs()->latest('called_at')->first()?->called_at?->isPast() ? 'danger' : 'success'),
                TextColumn::make('created_at')
                    ->label('Date Added')
                    ->date('d/m/Y'),
                BadgeColumn::make('conversion_status')
                    ->label('Conversion')
                    ->getStateUsing(fn (Customer $record): string => $record->orders()->exists() ? 'Converted' : 'Pending')
                    ->colors([
                        'success' => 'Converted',
                        'warning' => 'Pending',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('segment')
                    ->label('Segment')
                    ->form([
                        Select::make('segment')
                            ->label('Segment')
                            ->options([
                                'all' => 'All Customers',
                                'most_purchases' => 'Most Purchases',
                                'not_called_3d' => 'Not Called in 3 Days',
                                'not_called_7d' => 'Not Called in 7 Days',
                                'not_called_15d' => 'Not Called in 15 Days',
                                'not_called_20d' => 'Not Called in 20 Days',
                                'not_called_30d' => 'Not Called in 30 Days',
                                'never_called' => 'Never Called',
                                'no_purchases' => 'No Purchases',
                                'new_call_3d' => 'New - Call in 3 Days',
                            ])
                            ->default('all')
                            ->selectablePlaceholder(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (($data['segment'] ?? null) && $data['segment'] !== 'all') {
                            self::applySegmentFilter($query, $data['segment']);
                        }

                        return $query;
                    }),
                Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('From Date')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                        DatePicker::make('created_until')
                            ->label('To Date')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export Portfolio')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $customers = Customer::query()
                            ->where('rep_id', auth()->id())
                            ->where('rep_acceptance_status', 'accepted')
                            ->get();

                        $data = [];
                        foreach ($customers as $customer) {
                            $data[] = [
                                $customer->customer_name,
                                $customer->phone_number,
                                $customer->address,
                                $customer->city,
                                $customer->created_at->format('d/m/Y'),
                                $customer->orders()->where('status', 'delivered')->count(),
                                $customer->orders()->exists() ? 'Yes' : 'No',
                            ];
                        }

                        return response()->streamDownload(function () use ($data) {
                            $file = fopen('php://output', 'w');
                            fputcsv($file, ['Customer Name', 'Phone', 'Address', 'City', 'Date Added', 'Total Purchases', 'Converted']);
                            foreach ($data as $row) {
                                fputcsv($file, $row);
                            }
                            fclose($file);
                        }, 'lead_personal_portfolio_export_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment',
                        ]);
                    }),
            ])
            ->paginated(false);
    }

    public static function applySegmentFilter(Builder $query, string $segment): void
    {
        match ($segment) {
            'most_purchases' => $query
                ->withCount(['orders as orders_count' => fn ($q) => $q->where('status', 'delivered')])
                ->orderByDesc('orders_count'),
            'not_called_3d' => $query->whereDoesntHave('callLogs', fn ($q) => $q->where('called_at', '>=', now()->subDays(3))),
            'not_called_7d' => $query->whereDoesntHave('callLogs', fn ($q) => $q->where('called_at', '>=', now()->subDays(7))),
            'not_called_15d' => $query->whereDoesntHave('callLogs', fn ($q) => $q->where('called_at', '>=', now()->subDays(15))),
            'not_called_20d' => $query->whereDoesntHave('callLogs', fn ($q) => $q->where('called_at', '>=', now()->subDays(20))),
            'not_called_30d' => $query->whereDoesntHave('callLogs', fn ($q) => $q->where('called_at', '>=', now()->subDays(30))),
            'never_called' => $query->whereDoesntHave('callLogs'),
            'no_purchases' => $query->whereDoesntHave('orders', fn ($q) => $q->where('status', 'delivered')),
            'new_call_3d' => $query->whereHas('reps', function ($q) {
                $q->where('users.id', auth()->id())
                    ->where('customer_rep.created_at', '>=', now()->subDays(3));
            })->whereDoesntHave('callLogs', function ($q) {
                $q->where('called_at', '>=', now()->subDays(3));
            }),
            default => null,
        };
    }
}
