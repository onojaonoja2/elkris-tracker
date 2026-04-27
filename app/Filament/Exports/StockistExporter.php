<?php

namespace App\Filament\Exports;

use App\Models\Stockist;
use App\Models\StockistTransaction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class StockistExporter extends Exporter
{
    protected static ?string $model = Stockist::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name'),
            ExportColumn::make('phone'),
            ExportColumn::make('region'),
            ExportColumn::make('state'),
            ExportColumn::make('city'),
            ExportColumn::make('address'),
            ExportColumn::make('stock_balance')->label('Stock Value (₦)'),
            ExportColumn::make('total_units')->state(function (Stockist $record) {
                return $record->stocks()->sum('quantity');
            }),
            ExportColumn::make('last_received_date')->state(function (Stockist $record) {
                $lastTx = StockistTransaction::where('stockist_id', $record->id)
                    ->where('type', 'received')
                    ->latest('transaction_date')
                    ->first();

                return $lastTx ? $lastTx->transaction_date->format('d/m/Y') : 'N/A';
            }),
            ExportColumn::make('supervisor.name'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your stockist export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
