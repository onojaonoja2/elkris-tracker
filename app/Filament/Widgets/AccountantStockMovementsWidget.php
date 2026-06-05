<?php

namespace App\Filament\Widgets;

use App\Models\StockTransfer;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class AccountantStockMovementsWidget extends TableWidget
{
    protected static ?string $heading = 'Stock Movements for Verification';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->role === 'accountant';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockTransfer::whereIn('status', ['dispatched', 'received'])
                    ->orderBy('updated_at', 'desc')
                    ->limit(50)
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
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'dispatched' => 'warning',
                        'received' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('dispatcher.name')
                    ->label('Dispatched By'),
                TextColumn::make('receiver.name')
                    ->label('Received By'),
                TextColumn::make('items')
                    ->label('Items')
                    ->formatStateUsing(fn (StockTransfer $record): string => $record->items->map(
                        fn ($item) => ($item->productType?->name ?? '').' x'.$item->quantity
                    )->implode(', '))
                    ->limit(60),
                TextColumn::make('updated_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('viewDispatchNote')
                    ->label('Dispatch Note')
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->action(function (StockTransfer $record) {
                        $pdf = Pdf::loadView('pdf.dispatch-note', ['transfer' => $record]);

                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            "dispatch-note-{$record->id}.pdf"
                        );
                    }),
                Action::make('viewReceivedNote')
                    ->label('Received Note')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->visible(fn (StockTransfer $record) => $record->status === 'received')
                    ->action(function (StockTransfer $record) {
                        $pdf = Pdf::loadView('pdf.dispatch-note', ['transfer' => $record]);

                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            "goods-received-{$record->id}.pdf"
                        );
                    }),
            ])
            ->paginated(false);
    }
}
