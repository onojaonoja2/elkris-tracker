<?php

namespace App\Filament\Exports;

use App\Models\DamagedStockReturn;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class DamagedStockReturnExporter extends Exporter
{
    protected static ?string $model = DamagedStockReturn::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('#'),
            ExportColumn::make('user.name')->label('Returned By'),
            ExportColumn::make('warehouse.name')->label('Warehouse'),
            ExportColumn::make('productType.name')->label('Product'),
            ExportColumn::make('grammage')->label('Weight (g)'),
            ExportColumn::make('quantity')->label('Quantity'),
            ExportColumn::make('reason')->label('Reason'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('approver.name')->label('Approved By'),
            ExportColumn::make('created_at')->label('Date'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your damaged stock return export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
