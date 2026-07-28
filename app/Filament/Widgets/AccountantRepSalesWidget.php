<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables\Columns\TextColumn;
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
        return $table
            ->query(
                User::where('role', 'rep')
                    ->with('lead')
                    ->withCount(['repCustomers as orders_count' => function ($q) {
                        $q->whereHas('orders', fn ($o) => $o->whereIn('status', ['delivered', 'confirmed', 'completed']));
                    }])
                    ->withExists(['repCustomers as has_sales' => function ($q) {
                        $q->whereHas('orders', fn ($o) => $o->whereIn('status', ['delivered', 'confirmed', 'completed']));
                    }])
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Rep'),
                TextColumn::make('my_id')
                    ->label('Rep ID')
                    ->placeholder('-'),
                TextColumn::make('lead.name')
                    ->label('Team Lead')
                    ->placeholder('Unassigned'),
                TextColumn::make('total_sales')
                    ->label('Total Sales (₦)')
                    ->money('NGN')
                    ->state(function (User $record): float {
                        return (float) $record->repCustomers()
                            ->whereHas('orders', fn ($q) => $q->whereIn('status', ['delivered', 'confirmed', 'completed']))
                            ->join('orders', 'orders.customer_id', '=', 'customers.id')
                            ->whereIn('orders.status', ['delivered', 'confirmed', 'completed'])
                            ->sum('orders.total_price');
                    }),
                TextColumn::make('orders_count')
                    ->label('Orders'),
            ])
            ->defaultSort('orders_count', 'desc')
            ->paginated(false);
    }
}
