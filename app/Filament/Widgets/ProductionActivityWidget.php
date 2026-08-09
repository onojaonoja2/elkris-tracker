<?php

namespace App\Filament\Widgets;

use App\Models\ProductionRun;
use App\Models\RawMaterial;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

use function url;

class ProductionActivityWidget extends BaseWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $lowStockCount = RawMaterial::whereNotNull('reorder_level')
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->count();

        $pendingReviewCount = ProductionRun::where('status', 'pending_review')->count();

        return [
            Stat::make('Low Stock Materials', $lowStockCount)
                ->description('Raw materials at or below reorder level')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'danger' : 'success')
                ->url(url('/admin/raw-materials')),

            Stat::make('Pending Production Reviews', $pendingReviewCount)
                ->description('Production runs awaiting accountant review')
                ->icon('heroicon-o-clipboard-document-check')
                ->color($pendingReviewCount > 0 ? 'warning' : 'success')
                ->url(url('/admin/production-runs')),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole([
            'production_management',
            'manager',
            'general_manager',
            'admin',
            'accountant',
            'general_accountant',
        ]);
    }
}
