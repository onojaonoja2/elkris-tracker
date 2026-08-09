<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SalesRecords\SalesRecordResource;
use App\Models\SalesRecord;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class AgentDailySalesWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user() && auth()->user()->hasAnyRole(['open_market', 'retail_market']);
    }

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $count = SalesRecord::where('agent_id', auth()->id())
            ->whereDate('created_at', today())
            ->count();

        $roleLabel = auth()->user()->getPrimaryRole() === 'open_market' ? 'Open Market' : 'Retail Market';

        return [
            Stat::make("{$roleLabel} Sales Records Today", $count)
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->url(SalesRecordResource::getUrl('index')),
        ];
    }
}
