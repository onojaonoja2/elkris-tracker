<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SalesRecords\SalesRecordResource;
use App\Filament\Resources\TrialOrders\TrialOrderResource;
use App\Models\AgentStock;
use App\Models\SalesRecord;
use App\Models\TrialOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class AgentStockCardsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user() && in_array(auth()->user()->role, ['direct_sales', 'open_market', 'retail_market']);
    }

    protected function getStats(): array
    {
        $userId = auth()->id();
        $role = auth()->user()->role;

        $stats = [];

        $stockCount = AgentStock::where('user_id', $userId)->where('quantity', '>', 0)->count();
        $totalQty = AgentStock::where('user_id', $userId)->sum('quantity');

        $stats[] = Stat::make('Products in Stock', $stockCount)
            ->description($totalQty > 0 ? "{$totalQty} total units" : 'No stock allocated')
            ->icon('heroicon-o-cube')
            ->color($stockCount > 0 ? 'success' : 'gray');

        if ($role === 'direct_sales') {
            $todayTrialOrders = TrialOrder::where('agent_id', $userId)
                ->whereDate('created_at', today())
                ->get();

            $trialCount = $todayTrialOrders->count();
            $trialValue = $todayTrialOrders->sum('total_value');

            $stats[] = Stat::make("Today's Trial Orders", $trialCount)
                ->description($trialValue > 0 ? '₦'.number_format($trialValue, 2) : 'No trial orders today')
                ->icon('heroicon-o-clipboard-document-list')
                ->color($trialCount > 0 ? 'primary' : 'gray')
                ->url(TrialOrderResource::getUrl('index'));

            $stats[] = Stat::make("Today's Trial Value", '₦'.number_format($trialValue, 2))
                ->description($trialCount.' order'.($trialCount !== 1 ? 's' : ''))
                ->icon('heroicon-o-currency-dollar')
                ->color($trialValue > 0 ? 'success' : 'gray')
                ->url(TrialOrderResource::getUrl('index'));
        }

        if (in_array($role, ['open_market', 'retail_market'])) {
            $todaySales = SalesRecord::where('agent_id', $userId)
                ->whereDate('created_at', today())
                ->get();

            $salesCount = $todaySales->count();
            $salesValue = $todaySales->sum('total_value');

            $roleLabel = $role === 'open_market' ? 'Open Market' : 'Retail Market';

            $stats[] = Stat::make("{$roleLabel} Sales Today", $salesCount)
                ->description($salesValue > 0 ? '₦'.number_format($salesValue, 2) : 'No sales records today')
                ->icon('heroicon-o-document-text')
                ->color($salesCount > 0 ? 'primary' : 'gray')
                ->url(SalesRecordResource::getUrl('index'));

            $stats[] = Stat::make("{$roleLabel} Sales Value", '₦'.number_format($salesValue, 2))
                ->description($salesCount.' record'.($salesCount !== 1 ? 's' : ''))
                ->icon('heroicon-o-currency-dollar')
                ->color($salesValue > 0 ? 'success' : 'gray')
                ->url(SalesRecordResource::getUrl('index'));
        }

        $topStocks = AgentStock::where('user_id', $userId)
            ->where('quantity', '>', 0)
            ->orderBy('quantity', 'desc')
            ->limit(3)
            ->get();

        foreach ($topStocks as $stock) {
            $stats[] = Stat::make($stock->product_name." ({$stock->grammage}g)", $stock->quantity)
                ->description('In stock')
                ->icon('heroicon-o-cube')
                ->color($stock->quantity > 5 ? 'success' : 'warning');
        }

        return $stats;
    }
}
