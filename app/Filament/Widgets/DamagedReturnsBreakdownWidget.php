<?php

namespace App\Filament\Widgets;

use App\Models\DamagedStockReturn;
use App\Models\Warehouse;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class DamagedReturnsBreakdownWidget extends TableWidget
{
    protected static ?string $heading = 'Damaged Stock Returns Breakdown';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'supervisor', 'accountant', 'general_manager', 'general_accountant']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => DamagedStockReturn::with(['warehouse', 'productType', 'user', 'approver'])
                ->orderBy('created_at', 'desc'))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Returned By'),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse'),
                TextColumn::make('productType.name')
                    ->label('Product'),
                TextColumn::make('grammage')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state.'g'),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->sortable(),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(40),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn () => Warehouse::pluck('name', 'id')),
            ])
            ->paginated(15);
    }
}
