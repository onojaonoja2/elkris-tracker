<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class CreatedPerRepChart extends ChartWidget
{
    protected ?string $heading = 'Rep Performance: New Customers (Past 7 Days)';

    protected int | string | array $columnSpan = 'full';

    // Only allow Admin and Lead to see this widget
    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'lead']);
    }

    protected function getData(): array
    {
        $results = User::query()
            ->where('role', 'rep')
            ->withCount([
                'repCustomers as created_count' => fn ($query) => $query->where('created_at', '>=', now()->subDays(7)),
                'repCustomers as updated_count' => fn ($query) => $query->where('updated_at', '>=', now()->subDays(7))
            ])
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'New Customers',
                    'data' => $results->pluck('created_count')->toArray(),
                    // Using a sleek Emerald Green for "New"
                    'backgroundColor' => '#10b981', 
                    'borderColor' => '#059669',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Record Updates',
                    'data' => $results->pluck('updated_count')->toArray(),
                    // Using a modern Royal Purple for "Updates"
                    'backgroundColor' => '#8b5cf6', 
                    'borderColor' => '#7c3aed',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $results->pluck('name')->toArray(),
        ];
    }
    
    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false, // Cleaner look without vertical lines
                    ],
                ],
            ],
            'elements' => [
                'bar' => [
                    'borderRadius' => 4, // Slightly rounded corners for a modern feel
                    'borderSkipped' => false,
                ],
            ],
            // Adjust bar thickness here
            'barPercentage' => 0.5,      // Controls width of individual bars (0.0 to 1.0)
            'categoryPercentage' => 0.2, // Controls width of the group (0.0 to 1.0)
        ];
    }
}
