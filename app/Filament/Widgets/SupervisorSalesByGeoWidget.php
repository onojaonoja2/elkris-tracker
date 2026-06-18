<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use App\Models\State;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class SupervisorSalesByGeoWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Sales by Region';

    protected int|string|array $columnSpan = 'full';

    #[Url]
    public string $geoTab = 'lga';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function getTabs(): array
    {
        return [
            'lga' => 'LGA',
            'state' => 'State',
            'region' => 'Region',
            'country' => 'Country',
        ];
    }

    public function table(Table $table): Table
    {
        $from = Session::get('supervisor_date_from', now()->startOfDay()->toDateTimeString());
        $to = Session::get('supervisor_date_to', now()->endOfDay()->toDateTimeString());

        $baseQuery = SalesRecord::query()
            ->join('users', 'sales_records.agent_id', '=', 'users.id')
            ->leftJoin('lgas', 'users.lga_id', '=', 'lgas.id')
            ->leftJoin('states', 'lgas.state_id', '=', 'states.id')
            ->leftJoin('regions', 'states.region_id', '=', 'regions.id')
            ->where('users.role', 'community_sales_representative')
            ->whereBetween('sales_records.created_at', [$from, $to]);

        $aggregates = match ($this->geoTab) {
            'lga' => $baseQuery
                ->select(
                    'lgas.name as geo_name',
                    'states.name as parent_name',
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN sales_records.status = 'receipt_uploaded' THEN 1 ELSE 0 END) as pending"),
                    DB::raw("SUM(CASE WHEN sales_records.status = 'approved' THEN 1 ELSE 0 END) as approved"),
                    DB::raw("SUM(CASE WHEN sales_records.status = 'rejected' THEN 1 ELSE 0 END) as rejected"),
                    DB::raw('COALESCE(SUM(sales_records.total_value), 0) as total_value'),
                )
                ->groupBy('lgas.name', 'states.name')
                ->orderBy('geo_name')
                ->get()
                ->keyBy('geo_name'),

            'state' => $baseQuery
                ->select(
                    'states.name as geo_name',
                    'regions.name as parent_name',
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN sales_records.status = 'receipt_uploaded' THEN 1 ELSE 0 END) as pending"),
                    DB::raw("SUM(CASE WHEN sales_records.status = 'approved' THEN 1 ELSE 0 END) as approved"),
                    DB::raw("SUM(CASE WHEN sales_records.status = 'rejected' THEN 1 ELSE 0 END) as rejected"),
                    DB::raw('COALESCE(SUM(sales_records.total_value), 0) as total_value'),
                )
                ->groupBy('states.name', 'regions.name')
                ->orderBy('geo_name')
                ->get()
                ->keyBy('geo_name'),

            'region' => $baseQuery
                ->select(
                    'regions.name as geo_name',
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN sales_records.status = 'receipt_uploaded' THEN 1 ELSE 0 END) as pending"),
                    DB::raw("SUM(CASE WHEN sales_records.status = 'approved' THEN 1 ELSE 0 END) as approved"),
                    DB::raw("SUM(CASE WHEN sales_records.status = 'rejected' THEN 1 ELSE 0 END) as rejected"),
                    DB::raw('COALESCE(SUM(sales_records.total_value), 0) as total_value'),
                )
                ->groupBy('regions.name')
                ->orderBy('geo_name')
                ->get()
                ->keyBy('geo_name'),

            'country' => collect([$baseQuery
                ->select(
                    DB::raw("'Nigeria' as geo_name"),
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN sales_records.status = 'receipt_uploaded' THEN 1 ELSE 0 END) as pending"),
                    DB::raw("SUM(CASE WHEN sales_records.status = 'approved' THEN 1 ELSE 0 END) as approved"),
                    DB::raw("SUM(CASE WHEN sales_records.status = 'rejected' THEN 1 ELSE 0 END) as rejected"),
                    DB::raw('COALESCE(SUM(sales_records.total_value), 0) as total_value'),
                )
                ->first(),
            ])->keyBy('geo_name'),
        };

        $columns = match ($this->geoTab) {
            'lga' => [
                TextColumn::make('geo_name')->label('LGA')->searchable()->sortable(),
                TextColumn::make('parent_name')->label('State')->sortable(),
            ],
            'state' => [
                TextColumn::make('geo_name')->label('State')->searchable()->sortable(),
                TextColumn::make('parent_name')->label('Region')->sortable(),
            ],
            'region' => [
                TextColumn::make('geo_name')->label('Region')->searchable()->sortable(),
            ],
            default => [
                TextColumn::make('geo_name')->label('Country'),
            ],
        };

        return $table
            ->query(fn () => State::query()->whereRaw('1 = 0'))
            ->columns(array_merge($columns, [
                TextColumn::make('total')
                    ->label('Records')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->geo_name)?->total ?? 0)
                    ->numeric()
                    ->sortable(),

                TextColumn::make('approved')
                    ->label('Approved')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->geo_name)?->approved ?? 0)
                    ->numeric()
                    ->color('success'),

                TextColumn::make('pending')
                    ->label('Pending')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->geo_name)?->pending ?? 0)
                    ->numeric()
                    ->color('warning'),

                TextColumn::make('rejected')
                    ->label('Rejected')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->geo_name)?->rejected ?? 0)
                    ->numeric()
                    ->color('danger'),

                TextColumn::make('total_value')
                    ->label('Total Value')
                    ->getStateUsing(fn ($record): string => '₦'.number_format($aggregates->get($record->geo_name)?->total_value ?? 0, 2))
                    ->money('NGN')
                    ->sortable(),
            ]))
            ->paginated(false);
    }
}
