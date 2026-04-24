<?php

namespace App\Filament\Resources\TrialOrders\Pages;

use App\Filament\Resources\TrialOrders\TrialOrderResource;
use App\Filament\Resources\TrialOrders\Widgets\SupervisorTrialStatsWidget;
use App\Models\Stockist;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;

class ListTrialOrders extends ListRecords
{
    protected static string $resource = TrialOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => auth()->user()->role === 'field_agent'),

            Action::make('filter_by_location')
                ->label('Filter by Location')
                ->icon('heroicon-o-funnel')
                ->form([
                    Select::make('state_filter')
                        ->label('Select State')
                        ->options(function () {
                            $stockists = Stockist::where('supervisor_id', auth()->id())
                                ->select('state')
                                ->distinct()
                                ->pluck('state')
                                ->toArray();

                            return array_combine($stockists, $stockists);
                        })
                        ->placeholder('All States'),
                ])
                ->action(function (array $data) {
                    return redirect()->to(route('filament.admin.resources.trial-orders.index', ['state' => $data['state_filter'] ?? null]));
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        if (auth()->user()->role === 'supervisor') {
            return [
                SupervisorTrialStatsWidget::class,
            ];
        }

        return [];
    }

    protected function getTableFilters(): array
    {
        $stateFilter = request()->get('state');

        return [
            SelectFilter::make('state')
                ->label('State')
                ->options(function () {
                    return Stockist::where('supervisor_id', auth()->id())
                        ->select('state')
                        ->distinct()
                        ->pluck('state', 'state')
                        ->toArray();
                })
                ->query(function ($query, $data) use ($stateFilter) {
                    if ($stateFilter) {
                        $query->whereHas('agent', function ($q) use ($stateFilter) {
                            $q->whereJsonContains('assigned_cities', function ($cityQuery) use ($stateFilter) {
                                $cityQuery->select('city')
                                    ->from('stockists')
                                    ->whereColumn('city', DB::raw('ANY(users.assigned_cities)'))
                                    ->where('state', $stateFilter);
                            });
                        });
                    }
                }),
        ];
    }
}
