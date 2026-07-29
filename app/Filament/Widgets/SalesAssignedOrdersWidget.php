<?php

namespace App\Filament\Widgets;

use App\Filament\Exports\OrderExporter;
use App\Models\Order;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class SalesAssignedOrdersWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'My Assigned Orders';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        $userId = auth()->id();

        return $table
            ->query(
                fn () => Order::where('assigned_by', $userId)
                    ->with(['customer', 'assignedTo', 'products'])
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('id')
                    ->label('Order #')
                    ->sortable(),

                TextColumn::make('customer.customer_name')
                    ->label('Customer')
                    ->searchable(),

                TextColumn::make('assignedTo.name')
                    ->label('Assigned To')
                    ->placeholder('-'),

                TextColumn::make('assignment_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => $state->color()),

                TextColumn::make('total_price')
                    ->label('Value')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('assigned_at')
                    ->label('Assigned At')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->exporter(OrderExporter::class),
            ])
            ->paginated([10, 25]);
    }
}
