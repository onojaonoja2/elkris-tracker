<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class AgentCustomerViewWidget extends BaseWidget
{
    protected static ?string $heading = 'Agent Customer Overview';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['supervisor', 'manager', 'accountant', 'admin', 'general_manager', 'general_accountant']);
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $query = Customer::query()->with('orders', 'salesRecords');

        if ($user->hasRole('supervisor')) {
            $agentIds = User::where('lead_id', $user->id)
                ->orWhere('portfolio_agent_id', $user->id)
                ->pluck('id');
            $query->whereIn('agent_id', $agentIds)->orWhereIn('lead_id', $agentIds);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('agent.name')->label('Agent')->searchable()->sortable(),
                TextColumn::make('customer_name')->searchable()->sortable(),
                TextColumn::make('phone_number')->searchable(),
                TextColumn::make('lifetime_value')
                    ->label('Lifetime Sales')
                    ->money('NGN')
                    ->state(fn (Customer $record): float => $record->orders()->sum('total_price')),
                TextColumn::make('period_sales')
                    ->label('Sales (Period)')
                    ->money('NGN')
                    ->state(function (Customer $record): float {
                        $from = request()->input('tableFilters.date_filter.from', now()->startOfDay()->toDateString());
                        $to = request()->input('tableFilters.date_filter.to', now()->endOfDay()->toDateString());

                        return $record->orders()
                            ->whereDate('created_at', '>=', $from)
                            ->whereDate('created_at', '<=', $to)
                            ->sum('total_price');
                    }),
                TextColumn::make('created_at')->label('Added')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('agent_id')
                    ->label('Filter by Agent')
                    ->options(fn () => User::whereIn('role', [
                        'field_agent', 'community_sales_representative',
                        'open_market', 'retail_market', 'sales',
                    ])->pluck('name', 'id'))
                    ->searchable(),
                Filter::make('date_filter')
                    ->label('Sales Period')
                    ->form([
                        DatePicker::make('from')
                            ->label('From')
                            ->default(now()->startOfDay())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                        DatePicker::make('to')
                            ->label('To')
                            ->default(now()->endOfDay())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $dateFilter = $this->tableFilters['date_filter'] ?? [];
                        $from = $dateFilter['from'] ?? now()->startOfDay()->toDateString();
                        $to = $dateFilter['to'] ?? now()->endOfDay()->toDateString();

                        $records = $this->getFilteredQuery()->get();

                        return response()->streamDownload(function () use ($records, $from, $to) {
                            $file = fopen('php://output', 'w');
                            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                            fputcsv($file, ['Agent', 'Customer Name', 'Phone', 'Lifetime Sales (₦)', 'Period Sales (₦)', 'Added Date']);

                            foreach ($records as $record) {
                                $lifetime = $record->orders()->sum('total_price');
                                $period = $record->orders()
                                    ->whereDate('created_at', '>=', $from)
                                    ->whereDate('created_at', '<=', $to)
                                    ->sum('total_price');

                                fputcsv($file, [
                                    $record->agent?->name ?? 'N/A',
                                    $record->customer_name,
                                    $record->phone_number,
                                    number_format($lifetime, 2),
                                    number_format($period, 2),
                                    $record->created_at->format('d/m/Y'),
                                ]);
                            }

                            fclose($file);
                        }, 'agent_customer_overview_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
