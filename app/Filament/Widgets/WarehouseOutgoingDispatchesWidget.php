<?php

namespace App\Filament\Widgets;

use App\Models\StockTransfer;
use App\Support\DashboardDateScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class WarehouseOutgoingDispatchesWidget extends TableWidget
{
    protected static ?string $heading = 'Dispatched Stocks Awaiting Receipt Confirmation';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasRole('warehouse_manager');
    }

    public function table(Table $table): Table
    {
        $warehouseIds = auth()->user()->managedWarehouses()->pluck('id');

        return $table
            ->query(
                fn (): Builder => DashboardDateScope::scope(
                    StockTransfer::whereIn('from_warehouse_id', $warehouseIds)
                        ->where('status', 'dispatched')
                        ->orderBy('created_at', 'desc'),
                    'created_at'
                )
            )
            ->columns([
                TextColumn::make('id')
                    ->label('Ref #'),
                TextColumn::make('toWarehouse.name')
                    ->label('To Warehouse')
                    ->placeholder('-'),
                TextColumn::make('toAgent.name')
                    ->label('To Agent')
                    ->placeholder('-'),
                TextColumn::make('dispatcher.name')
                    ->label('Dispatched By'),
                TextColumn::make('items')
                    ->label('Items')
                    ->formatStateUsing(fn (StockTransfer $record): string => $record->items->map(
                        fn ($item) => ($item->productType?->name ?? '').' x'.$item->quantity
                    )->implode(', '))
                    ->limit(60),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->placeholder('-')
                    ->limit(40),
            ])
            ->recordActions([
                Action::make('printPdf')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->action(function (StockTransfer $record) {
                        $pdf = Pdf::loadView('pdf.dispatch-note', ['transfer' => $record]);

                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            "dispatch-note-{$record->id}.pdf"
                        );
                    }),
            ])
            ->paginated(20);
    }
}
