<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class OverdueCreditSalesWidget extends TableWidget
{
    protected static ?string $heading = 'Overdue Credit Sales';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['community_sales_representative', 'open_market', 'retail_market']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SalesRecord::outstanding()
                ->where('agent_id', auth()->id())
                ->where('expected_collection_date', '<', now()->toDateString())
                ->latest('expected_collection_date'))
            ->columns([
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('total_value')
                    ->label('Amount (₦)')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('expected_collection_date')
                    ->label('Expected Date')
                    ->date()
                    ->sortable()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'gray'),
                TextColumn::make('days_overdue')
                    ->label('Days Overdue')
                    ->state(fn (SalesRecord $record): int => max(0, (int) now()->startOfDay()->diffInDays($record->expected_collection_date)))
                    ->numeric()
                    ->color('danger'),
            ])
            ->emptyStateHeading('No overdue credit sales')
            ->emptyStateDescription('Great job! You have no overdue credit sales.')
            ->paginated(false)
            ->defaultPaginationPageOption(10);
    }
}
