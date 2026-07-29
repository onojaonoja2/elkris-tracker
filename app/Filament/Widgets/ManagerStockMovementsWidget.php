<?php

namespace App\Filament\Widgets;

use App\Enums\StockTransferStatus;
use App\Filament\Exports\StockTransferExporter;
use App\Models\StockTransfer;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ManagerStockMovementsWidget extends TableWidget
{
    protected static ?string $heading = 'All Stock Movements';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => StockTransfer::query()
                    ->with(['fromWarehouse', 'toWarehouse', 'fromAgent', 'toAgent', 'dispatcher', 'receiver', 'items.productType'])
                    ->latest()
            )
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('from_location')
                    ->label('From')
                    ->getStateUsing(fn (StockTransfer $record): string => $record->fromWarehouse?->name
                        ?? $record->fromAgent?->name
                        ?? 'N/A'),
                TextColumn::make('to_location')
                    ->label('To')
                    ->getStateUsing(fn (StockTransfer $record): string => $record->toWarehouse?->name
                        ?? $record->toAgent?->name
                        ?? 'N/A'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (StockTransferStatus $state): string => $state->color()),
                TextColumn::make('items_summary')
                    ->label('Items')
                    ->getStateUsing(fn (StockTransfer $record): string => $record->items->map(
                        fn ($item) => ($item->productType?->name ?? 'Unknown').' x'.$item->quantity
                    )->implode(', '))
                    ->limit(60),
                TextColumn::make('dispatcher.name')
                    ->label('Dispatched By')
                    ->placeholder('-'),
                TextColumn::make('receiver.name')
                    ->label('Received By')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->exporter(StockTransferExporter::class),
            ])
            ->paginated(25);
    }
}
