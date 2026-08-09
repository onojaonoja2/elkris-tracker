<?php

namespace App\Filament\Exports;

use App\Models\AgentStock;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class AgentStockExporter extends Exporter
{
    protected static ?string $model = AgentStock::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('agent.name')->label('Agent'),
            ExportColumn::make('product_name')->label('Product'),
            ExportColumn::make('grammage')->label('Weight (g)'),
            ExportColumn::make('quantity')->label('Quantity'),
            ExportColumn::make('created_at')->label('Date Added'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your agent stock export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }
}
