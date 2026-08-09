<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class AccountantRepSalesWidget extends TableWidget
{
    protected static ?string $heading = 'Rep & Lead Sales Breakdown';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['accountant', 'general_accountant']);
    }

    public function table(Table $table): Table
    {
        $role = $this->tableFilters['role']['value'] ?? 'rep';
        $relation = $role === 'lead' ? 'leadCustomers' : 'repCustomers';

        return $table
            ->query(
                User::where('role', $role)
                    ->with('lead')
                    ->withCount(["{$relation} as orders_count" => function ($query) {
                        $query->whereHas('orders', fn ($orders) => $orders->whereIn('status', [
                            'delivered',
                            'confirmed',
                            'completed',
                        ]));
                    }])
            )
            ->columns([
                TextColumn::make('name')
                    ->label(ucfirst($role)),
                TextColumn::make('my_id')
                    ->label('ID')
                    ->placeholder('-'),
                TextColumn::make('lead.name')
                    ->label('Team Lead')
                    ->placeholder('Unassigned'),
                TextColumn::make('total_sales')
                    ->label('Total Sales (₦)')
                    ->money('NGN')
                    ->state(function (User $record): float {
                        return $this->salesTotal($record);
                    }),
                TextColumn::make('orders_count')
                    ->label('Orders'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Show')
                    ->options([
                        'rep' => 'Reps',
                        'lead' => 'Leads',
                    ])
                    ->default('rep'),
            ])
            ->defaultSort('orders_count', 'desc')
            ->recordActions([
                Action::make('viewRepSales')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(fn (User $record) => $this->dispatch('open-rep-sales-breakdown', userId: $record->id)),
            ])
            ->paginated(false);
    }

    protected function salesTotal(User $record): float
    {
        $relation = $record->role === 'lead' ? 'leadCustomers' : 'repCustomers';

        return (float) $record->{$relation}()
            ->whereHas('orders', fn ($orders) => $orders->whereIn('status', [
                'delivered',
                'confirmed',
                'completed',
            ]))
            ->join('orders', 'orders.customer_id', '=', 'customers.id')
            ->whereIn('orders.status', ['delivered', 'confirmed', 'completed'])
            ->sum('orders.total_price');
    }
}
