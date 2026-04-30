<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class OrdersPerCityChart extends ChartWidget
{
    protected ?string $heading = 'Total Orders Per City';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->role === 'manager';
    }

    protected function getData(): array
    {
        $data = Order::where('status', '!=', 'cancelled')
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
