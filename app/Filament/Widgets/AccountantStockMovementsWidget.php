<?php

namespace App\Filament\Widgets;

use App\Models\StockTransfer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
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
        return in_array(auth()->user()->role, ['accountant', 'general_accountant']);
    }

    protected function getFilteredQuery()
    {
        $query = StockTransfer::whereIn('status', ['dispatched', 'received'])
            ->orderBy('updated_at', 'desc');

        $filters = $this->tableFilters['date_range'] ?? [];

        if ($filters['from'] ?? null) {
            $query->whereDate('updated_at', '>=', $filters['from']);
        }
        if ($filters['until'] ?? null) {
            $query->whereDate('updated_at', '<=', $filters['until']);
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getFilteredQuery())
            ->columns([
                TextColumn::make('id')
                    ->label('Ref #'),
                TextColumn::make('fromWarehouse.name')
                    ->label('From'),
                TextColumn::make('toWarehouse.name')
                    ->label('To Warehouse')
                    ->placeholder('-'),
                TextColumn::make('toAgent.name')
                    ->label('To Agent')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->badge(),
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
            ->filters([
                Filter::make('date_range')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('from')
                            ->label('From Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('until')
                            ->label('Until Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('updated_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('updated_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'From '.Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until '.Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
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
            ->headerActions([
                Action::make('export')
                    ->label('Export to Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $records = $this->getFilteredQuery()->get();
                        $data = [];
                        foreach ($records as $record) {
                            $data[] = [
                                $record->id,
                                $record->fromWarehouse?->name ?? ($record->fromAgent?->name ?? 'N/A'),
                                $record->toWarehouse?->name ?? '-',
                                $record->toAgent?->name ?? '-',
                                $record->status->value,
                                $record->dispatcher?->name ?? '-',
                                $record->receiver?->name ?? '-',
                                $record->items->map(fn ($item) => ($item->productType?->name ?? '').' x'.$item->quantity)->implode(', '),
                                $record->items->map(fn ($item) => ($item->productType?->name ?? '').' '.$item->grammage.'g')->implode(', '),
                                $record->updated_at->format('d/m/Y H:i'),
                            ];
                        }

                        return response()->streamDownload(function () use ($data) {
                            $file = fopen('php://output', 'w');
                            fputcsv($file, ['Ref #', 'From', 'To Warehouse', 'To Agent', 'Status', 'Dispatched By', 'Received By', 'Items', 'Weight', 'Date']);
                            foreach ($data as $row) {
                                fputcsv($file, $row);
                            }
                            fclose($file);
                        }, 'stock_movements_export_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment',
                        ]);
                    }),
            ])
            ->paginated(20);
    }
}
