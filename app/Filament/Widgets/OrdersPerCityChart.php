<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class OrdersPerCityChart extends ChartWidget
{
    protected ?string $heading = 'Total Orders Per City';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }

    protected function getData(): array
    {
        $data = Order::where('orders.status', '!=', 'cancelled')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->select('customers.city', DB::raw('count(*) as total_orders'))
            ->groupBy('customers.city')
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
