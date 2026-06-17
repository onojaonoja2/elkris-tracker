<?php

namespace App\Filament\Widgets;

use App\Models\StockTransfer;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class WarehouseRecentMovementsWidget extends TableWidget
{
    protected static ?string $heading = 'Recent Stock Movements';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->role === 'warehouse_manager';
    }

    public function table(Table $table): Table
    {
        $warehouseIds = auth()->user()->managedWarehouses()->pluck('id');

        return $table
            ->query(
                fn (): Builder => StockTransfer::where(function ($q) use ($warehouseIds) {
                    $q->whereIn('from_warehouse_id', $warehouseIds)
                        ->orWhereIn('to_warehouse_id', $warehouseIds);
                })
                    ->whereIn('status', ['dispatched', 'received'])
                    ->orderBy('updated_at', 'desc')
                    ->limit(20)
            )
            ->columns([
                TextColumn::make('id')
                    ->label('Ref #'),
                TextColumn::make('fromWarehouse.name')
                    ->label('From'),
                TextColumn::make('toWarehouse.name')
                    ->label('To Warehouse')
                    ->placeholder('-'),
                TextColumn::make('toStockist.name')
                    ->label('To Stockist')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('updated_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('printPdf')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->action(function (StockTransfer $record) {
                        $pdf = Pdf::loadView('pdf.dispatch-note', ['transfer' => $record]);

                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            "movement-{$record->id}.pdf"
                        );
                    }),
            ])
            ->paginated(false);
    }
}
