<?php

namespace App\Filament\Exports;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class UserExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')->label('Name'),
            ExportColumn::make('email')->label('Email'),
            ExportColumn::make('role')->label('Primary Role'),
            ExportColumn::make('additional_roles')
                ->label('Additional Roles')
                ->formatStateUsing(fn (?array $state): string => is_array($state) ? implode(', ', $state) : '-'),
            ExportColumn::make('is_active')->label('Active'),
            ExportColumn::make('phone')->label('Phone'),
            ExportColumn::make('lga.name')->label('LGA'),
            ExportColumn::make('state.name')->label('State'),
            ExportColumn::make('created_at')->label('Date Added'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your user export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

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
