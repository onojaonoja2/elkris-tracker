<?php

namespace App\Filament\Exports;

use App\Models\TrialOrder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class TrialOrderExporter extends Exporter
{
    protected static ?string $model = TrialOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('agent.name')->label('Field Agent'),
            ExportColumn::make('stockist.name')->label('Stockist'),
            ExportColumn::make('products')->state(function (TrialOrder $record) {
                return collect($record->products)->map(fn ($p) => "{$p['quantity']}x {$p['product_name']}")->implode(', ');
            }),
            ExportColumn::make('total_value'),
            ExportColumn::make('status'),
            ExportColumn::make('approver.name')->label('Approved By'),
            ExportColumn::make('created_at')->label('Date'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your trial order export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
