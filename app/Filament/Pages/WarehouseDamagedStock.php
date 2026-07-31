<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasDashboardDateFilter;
use App\Filament\Widgets\WarehouseDamagedStockTable;
use App\Models\DamagedInventory;
use App\Support\DashboardDateScope;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class WarehouseDamagedStock extends BasePage
{
    use HasDashboardDateFilter;

    protected static string $navigationRole = 'warehouse_manager';

    protected static ?string $slug = 'warehouse-damaged-stock';

    protected static ?string $navigationLabel = 'Damaged Stock';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxXMark;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.warehouse-damaged-stock';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('warehouse_manager');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('warehouse_manager');
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('warehouse_manager');
    }

    public function getWidgets(): array
    {
        return [
            WarehouseDamagedStockTable::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getDateFilterAction(),
            $this->getClearDateFilterAction(),
            Action::make('exportReport')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => $this->exportReport()),
        ];
    }

    protected function exportReport()
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $warehouseIds = auth()->user()->managedWarehouses()->pluck('id');

        $records = DamagedInventory::where(function ($query) use ($warehouseIds) {
            $query->whereIn('warehouse_id', $warehouseIds)
                ->orWhereIn('destination_warehouse_id', $warehouseIds);
        })
            ->with(['warehouse', 'destinationWarehouse', 'productType', 'damagedStockReturn'])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get();

        $filename = 'damaged_stock_'.date('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Date', 'Warehouse', 'Product', 'Weight (g)', 'Quantity', 'Status', 'Source Return', 'Destination', 'Received At', 'Destroyed At', 'Destroy Reason']);

            foreach ($records as $record) {
                fputcsv($handle, [
                    $record->created_at->format('d/m/Y H:i'),
                    $record->warehouse?->name ?? '-',
                    $record->productType?->name ?? '-',
                    $record->grammage,
                    $record->quantity,
                    $record->status,
                    $record->damagedStockReturn?->id ?? '-',
                    $record->destinationWarehouse?->name ?? '-',
                    $record->received_at?->format('d/m/Y H:i') ?? '-',
                    $record->destroyed_at?->format('d/m/Y H:i') ?? '-',
                    $record->destroy_reason ?? '-',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
