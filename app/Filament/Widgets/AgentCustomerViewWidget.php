<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\User;
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
                TextColumn::make('daily_sales')
                    ->label('Daily Sales')
                    ->money('NGN')
                    ->state(function (Customer $record): float {
                        $date = request()->input('tableFilters.date_filter.date', now()->toDateString());

                        return $record->orders()
                            ->whereDate('created_at', $date)
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
                    ->form([
                        DatePicker::make('date')->label('Sales Date')->default(now()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
