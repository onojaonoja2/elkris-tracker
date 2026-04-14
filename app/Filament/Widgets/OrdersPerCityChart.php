<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OrdersPerCityChart extends ChartWidget
{
    protected ?string $heading = 'Total Orders Per City';
    
    public static function canView(): bool
    {
        return auth()->user()->role === 'manager';
    }

    protected function getData(): array
    {
        $data = Customer::where('total_price', '>', 0)
            ->select('city', DB::raw('count(*) as total_orders'))
            ->groupBy('city')
            ->pluck('total_orders', 'city')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Successful Orders',
                    'data' => array_values($data),
                    'backgroundColor' => '#36A2EB',
                ],
            ],
            'labels' => array_map('ucfirst', array_keys($data)),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
