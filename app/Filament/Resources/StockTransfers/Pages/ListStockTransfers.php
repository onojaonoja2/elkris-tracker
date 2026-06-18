<?php

namespace App\Filament\Resources\StockTransfers\Pages;

use App\Filament\Resources\StockTransfers\StockTransferResource;
use App\Models\StockTransfer;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListStockTransfers extends ListRecords
{
    protected static string $resource = StockTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->action(function () {
                    $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'toAgent', 'items.productType', 'requester', 'approver', 'dispatcher'])
                        ->orderBy('created_at', 'desc')
                        ->get();

                    return response()->streamDownload(function () use ($transfers) {
                        $file = fopen('php://output', 'w');
                        fputcsv($file, ['Transfer #', 'From', 'To Warehouse', 'To CSR', 'Items', 'Status', 'Requested By', 'Approved By', 'Dispatched By', 'Date']);

                        foreach ($transfers as $t) {
                            $items = $t->items->map(fn ($item) => ($item->productType?->name ?? '')." {$item->grammage}g x".$item->quantity)->implode('; ');
                            fputcsv($file, [
                                $t->id,
                                $t->fromWarehouse?->name ?? $t->fromAgent?->name ?? '-',
                                $t->toWarehouse?->name ?? '-',
                                $t->toAgent?->name ?? '-',
                                $items,
                                $t->status->value,
                                $t->requester?->name ?? '-',
                                $t->approver?->name ?? '-',
                                $t->dispatcher?->name ?? '-',
                                $t->created_at->format('d/m/Y H:i'),
                            ]);
                        }
                        fclose($file);
                    }, 'stock_transfers_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                        'Content-Type' => 'text/csv',
                        'Content-Disposition' => 'attachment',
                    ]);
                }),
        ];
    }
}
