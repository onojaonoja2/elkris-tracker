<?php

namespace App\Filament\Resources\Orders\Widgets;

use App\Models\Product;
use App\Models\StockTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockBalanceWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        if (! in_array(auth()->user()->role, ['admin', 'sales'])) {
            return [];
        }

        $products = [
            ['name' => 'Elkris Oat Flour', 'grammage' => 5000],
            ['name' => 'Elkris Oat Flour', 'grammage' => 1300],
            ['name' => 'Elkris Oat Flour', 'grammage' => 650],
            ['name' => 'Elkris Plantain', 'grammage' => 1800],
            ['name' => 'Elkris Plantain', 'grammage' => 900],
            ['name' => 'Elkris Poundo Yam', 'grammage' => 1800],
        ];

        $stats = [];

        foreach ($products as $p) {
            $received = StockTransaction::where('type', 'received')
                ->where('product_name', $p['name'])
                ->where('grammage', $p['grammage'])
                ->sum('quantity');

            $disbursed = StockTransaction::where('type', 'disbursed')
                ->where('product_name', $p['name'])
                ->where('grammage', $p['grammage'])
                ->sum('quantity');

            $delivered = Product::whereHas('order', function ($q) {
                $q->where('status', 'delivered');
            })
                ->where('product_name', $p['name'])
                ->where('grammage', $p['grammage'])
                ->sum('quantity');

            $balance = $received - $disbursed - $delivered;

            $stats[] = Stat::make("{$p['name']} ({$p['grammage']}g)", $balance)
                ->description("Rec: $received | Disb: $disbursed | Del: $delivered")
                ->url(\App\Filament\Resources\StockTransactions\StockTransactionResource::getUrl('index', [
                    'tableFilters' => [
                        'product_name' => ['value' => $p['name']],
                        'grammage' => ['value' => $p['grammage']],
                    ]
                ]));
        }

        return $stats;
    }
}
