<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\StockTransfer;
use App\Models\User;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class ManagerAnalyticsWidget extends TableWidget
{
    protected static ?string $heading = 'System Overview';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function table(Table $table): Table
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $allTime = Carbon::now()->subYears(10);

        $metrics = collect([
            [
                'metric' => 'Total Customers',
                'today' => Customer::whereDate('created_at', $today)->count(),
                'this_month' => Customer::where('created_at', '>=', $thisMonth)->count(),
                'all_time' => Customer::count(),
            ],
            [
                'metric' => 'Orders',
                'today' => Order::whereDate('created_at', $today)->where('is_migrated_order', false)->count(),
                'this_month' => Order::where('created_at', '>=', $thisMonth)->where('is_migrated_order', false)->count(),
                'all_time' => Order::where('is_migrated_order', false)->count(),
            ],
            [
                'metric' => 'Sales Records',
                'today' => SalesRecord::whereDate('created_at', $today)->count(),
                'this_month' => SalesRecord::where('created_at', '>=', $thisMonth)->count(),
                'all_time' => SalesRecord::count(),
            ],
            [
                'metric' => 'Revenue (₦)',
                'today' => (float) Order::whereDate('created_at', $today)->where('is_migrated_order', false)->sum('total_price'),
                'this_month' => (float) Order::where('created_at', '>=', $thisMonth)->where('is_migrated_order', false)->sum('total_price'),
                'all_time' => (float) Order::where('is_migrated_order', false)->sum('total_price'),
            ],
            [
                'metric' => 'Calls Logged',
                'today' => CallLog::whereDate('called_at', $today)->count(),
                'this_month' => CallLog::where('called_at', '>=', $thisMonth)->count(),
                'all_time' => CallLog::count(),
            ],
            [
                'metric' => 'Stock Transfers',
                'today' => StockTransfer::whereDate('created_at', $today)->count(),
                'this_month' => StockTransfer::where('created_at', '>=', $thisMonth)->count(),
                'all_time' => StockTransfer::count(),
            ],
            [
                'metric' => 'Active Agents',
                'today' => '-',
                'this_month' => '-',
                'all_time' => User::whereIn('role', ['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])->active()->count(),
            ],
            [
                'metric' => 'Warehouse Stock (units)',
                'today' => '-',
                'this_month' => '-',
                'all_time' => (int) Inventory::sum('quantity'),
            ],
            [
                'metric' => 'Agent Stock (units)',
                'today' => '-',
                'this_month' => '-',
                'all_time' => (int) AgentStock::sum('quantity'),
            ],
        ]);

        $roleCounts = User::select('role', DB::raw('COUNT(*) as count'))
            ->where('is_active', true)
            ->groupBy('role')
            ->pluck('count', 'role');

        foreach ($roleCounts as $role => $count) {
            $metrics->push([
                'metric' => 'Active '.str_replace('_', ' ', (string) $role).'s',
                'today' => '-',
                'this_month' => '-',
                'all_time' => $count,
            ]);
        }

        return $table
            ->query(fn (): Builder => User::query()->whereRaw('1 = 0'))
            ->columns([
                TextColumn::make('metric')
                    ->label('Metric')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('today')
                    ->label('Today')
                    ->formatStateUsing(fn ($state): string => is_float($state) ? '₦'.number_format($state, 0) : (string) $state)
                    ->sortable(),
                TextColumn::make('this_month')
                    ->label('This Month')
                    ->formatStateUsing(fn ($state): string => is_float($state) ? '₦'.number_format($state, 0) : (string) $state)
                    ->sortable(),
                TextColumn::make('all_time')
                    ->label('All Time')
                    ->formatStateUsing(fn ($state): string => is_float($state) ? '₦'.number_format($state, 0) : (string) $state)
                    ->sortable(),
            ])
            ->paginated(false);
    }

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }
}
