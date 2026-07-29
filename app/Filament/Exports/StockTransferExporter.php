<?php

namespace App\Filament\Exports;

use App\Models\StockTransfer;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class StockTransferExporter extends Exporter
{
    protected static ?string $model = StockTransfer::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('#'),
            ExportColumn::make('fromWarehouse.name')
                ->label('From Warehouse')
                ->placeholder('-'),
            ExportColumn::make('fromAgent.name')
                ->label('From Agent')
                ->placeholder('-'),
            ExportColumn::make('toWarehouse.name')
                ->label('To Warehouse')
                ->placeholder('-'),
            ExportColumn::make('toAgent.name')
                ->label('To Agent')
                ->placeholder('-'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('dispatcher.name')->label('Dispatched By'),
            ExportColumn::make('receiver.name')->label('Received By'),
            ExportColumn::make('created_at')->label('Date'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your stock transfer export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
