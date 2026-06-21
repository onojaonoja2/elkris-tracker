<?php

namespace App\Filament\Widgets;

use App\Models\TrialOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class TrialOrdersByStateChart extends ChartWidget
{
    protected ?string $heading = 'Trial Orders by State';

    protected int|string|array $columnSpan = 6;

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'manager', 'general_manager']);
    }

    protected function getData(): array
    {
        $data = TrialOrder::select(
            DB::raw('lga_state.name as state_name'),
            DB::raw('COUNT(*) as total_orders')
        )
            ->leftJoin('users', 'trial_orders.agent_id', '=', 'users.id')
            ->leftJoin('lgas', 'users.lga_id', '=', 'lgas.id')
            ->leftJoin('states as lga_state', 'lgas.state_id', '=', 'lga_state.id')
            ->groupBy('lga_state.name')
            ->orderBy('total_orders', 'desc')
            ->pluck('total_orders', 'state_name')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Trial Orders',
                    'data' => array_values($data),
                    'backgroundColor' => '#F59E0B',
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
