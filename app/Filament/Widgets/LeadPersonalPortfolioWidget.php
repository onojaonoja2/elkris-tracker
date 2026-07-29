<?php

namespace App\Filament\Widgets;

use App\Filament\Exports\PortfolioCustomerExporter;
use App\Models\Customer;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
        return auth()->user()->hasRole('lead');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Customer::query()
                ->where('rep_acceptance_status', 'accepted')
                ->where(function ($q) {
                    $q->where('rep_id', auth()->id())
                        ->orWhere(function ($sub) {
                            $sub->where('lead_id', auth()->id())
                                ->whereNull('rep_id');
                        });
                }))
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
                    ->getStateUsing(fn ($record): int => $record->orders()->where('status', 'delivered')->where('is_migrated_order', false)->count())
                    ->sortable()
                    ->color(fn ($state): string => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('last_called')
                    ->label('Last Called')
                    ->getStateUsing(fn ($record): string => $record->callLogs()->latest('called_at')->first()?->called_at?->diffForHumans() ?? 'Never')
                    ->color(fn ($record): string => $record->callLogs()->latest('called_at')->first()?->called_at?->isPast() ? 'danger' : 'success'),
                TextColumn::make('created_at')
                    ->label('Date Added')
                    ->date('d/m/Y'),
                TextColumn::make('conversion_status')
                    ->label('Conversion')
                    ->badge()
                    ->getStateUsing(fn (Customer $record): string => $record->orders()->where('is_migrated_order', false)->exists() ? 'Converted' : 'Pending')
                    ->color(fn (string $state): string => $state === 'Converted' ? 'success' : 'warning'),
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
                ExportAction::make()
                    ->exporter(PortfolioCustomerExporter::class),
            ])
            ->paginated([10, 25, 50]);
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
