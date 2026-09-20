<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\AgentStock;
use App\Models\Order;
use App\Models\User;
use App\Support\DashboardDateScope;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class WarehouseCsrStockWidget extends TableWidget
{
    protected static ?string $heading = 'CSR Stock Summary';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('warehouse_manager') ?? false;
    }

    public function table(Table $table): Table
    {
        $csrIds = $this->csrIds();

        $stockCounts = AgentStock::whereIn('user_id', $csrIds)
            ->selectRaw('user_id, SUM(quantity) as total_qty, COUNT(*) as product_lines')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        [$from, $to] = DashboardDateScope::fromSession();

        $completedOrders = Order::query()
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to])
            ->where('status', OrderStatus::Delivered)
            ->whereIn('assigned_to', $csrIds)
            ->selectRaw('assigned_to, COUNT(*) as order_count, COALESCE(SUM(total_price), 0) as order_value')
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        return $table
            ->query(fn (): Builder => User::query()
                ->where('role', 'community_sales_representative')
                ->where('is_active', true)
                ->with(['state', 'lga'])
                ->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->label('CSR Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->placeholder('N/A'),
                TextColumn::make('lga.name')
                    ->label('LGA')
                    ->searchable(),
                TextColumn::make('state.name')
                    ->label('State')
                    ->searchable(),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Suspended'),
                TextColumn::make('stock_units')
                    ->label('Stock Units')
                    ->getStateUsing(fn (User $record): int => (int) ($stockCounts->get($record->id)?->total_qty ?? 0))
                    ->sortable(),
                TextColumn::make('product_lines')
                    ->label('Product Lines')
                    ->getStateUsing(fn (User $record): int => (int) ($stockCounts->get($record->id)?->product_lines ?? 0)),
                TextColumn::make('completed_orders')
                    ->label('Completed Orders')
                    ->getStateUsing(fn (User $record): int => (int) ($completedOrders->get($record->id)?->order_count ?? 0)),
                TextColumn::make('completed_value')
                    ->label('Completed Value')
                    ->getStateUsing(fn (User $record): float => (float) ($completedOrders->get($record->id)?->order_value ?? 0))
                    ->money('NGN'),
            ])
            ->filters([
                SelectFilter::make('csr_id')
                    ->label('CSR')
                    ->options(fn (): Collection => User::query()
                        ->where('role', 'community_sales_representative')
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, int $userId): Builder => $query->where('id', $userId),
                    )),
            ])
            ->recordActions([
                ViewAction::make('viewBreakdown')
                    ->label('View')
                    ->modalHeading(fn (?User $record): string => $record ? "{$record->name} — Overview" : 'View')
                    ->modalWidth('5xl')
                    ->infolist(fn (?User $record): array => $record ? $this->stockBreakdownInfolist($record) : []),
            ])
            ->headerActions([
                Action::make('exportCsrStock')
                    ->label('Export CSV')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('secondary')
                    ->action(fn () => $this->exportCsv()),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50, -1]);
    }

    protected function stockBreakdownInfolist(User $record): array
    {
        $record->loadMissing(['state', 'lga', 'agentStocks']);

        $stockTotal = $record->agentStocks->sum('quantity');

        [$from, $to] = DashboardDateScope::fromSession();

        $completed = Order::query()
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to])
            ->where('status', OrderStatus::Delivered)
            ->where('assigned_to', $record->id)
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_price), 0) as order_value')
            ->first();

        $pending = Order::query()
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to])
            ->pendingDelivery()
            ->where('assigned_to', $record->id)
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_price), 0) as order_value')
            ->first();

        $isDefaultRange = $from->is(now()->startOfDay()->subYears(50));

        $periodLabel = $isDefaultRange ? 'All time' : $from->format('d M Y').' - '.$to->format('d M Y');

        return [
            Section::make('CSR Details')
                ->columns(5)
                ->schema([
                    TextEntry::make('name')->label('Name'),
                    TextEntry::make('phone')->label('Phone')->placeholder('N/A'),
                    TextEntry::make('lga.name')->label('LGA')->default('N/A'),
                    TextEntry::make('state.name')->label('State')->default('N/A'),
                    TextEntry::make('total_stock')->label('Total Stock Units')
                        ->state(fn (): int => $stockTotal),
                ]),
            Section::make('Stock Breakdown')
                ->schema([
                    RepeatableEntry::make('agentStocks')
                        ->label('Stocks')
                        ->schema([
                            TextEntry::make('product_name')->label('Product'),
                            TextEntry::make('grammage')
                                ->label('Weight')
                                ->formatStateUsing(fn ($state) => $state.'g'),
                            TextEntry::make('quantity')->label('Quantity'),
                        ])
                        ->columns(3),
                ]),
            Section::make('Order Summary')
                ->columns(5)
                ->schema([
                    TextEntry::make('period')->label('Period')
                        ->state(fn (): string => $periodLabel),
                    TextEntry::make('completed_orders')->label('Completed Orders')
                        ->state(fn (): int => (int) ($completed->order_count ?? 0)),
                    TextEntry::make('completed_value')->label('Completed Value')
                        ->state(fn (): float => (float) ($completed->order_value ?? 0))
                        ->money('NGN'),
                    TextEntry::make('pending_orders')->label('Pending Orders Assigned')
                        ->state(fn (): int => (int) ($pending->order_count ?? 0)),
                    TextEntry::make('pending_value')->label('Pending Order Value')
                        ->state(fn (): float => (float) ($pending->order_value ?? 0))
                        ->money('NGN'),
                ]),
        ];
    }

    protected function exportCsv()
    {
        $userIds = $this->csrIds();

        $filterValue = $this->tableFilters['csr_id']['value'] ?? null;
        if ($filterValue !== null && $filterValue !== '') {
            $userIds = $userIds->intersect([(int) $filterValue]);
        }

        $stockCounts = AgentStock::whereIn('user_id', $userIds)
            ->selectRaw('user_id, SUM(quantity) as total_qty')
            ->groupBy('user_id')
            ->pluck('total_qty', 'user_id');

        [$from, $to] = DashboardDateScope::fromSession();

        $orderCounts = Order::query()
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to])
            ->where('status', OrderStatus::Delivered)
            ->whereIn('assigned_to', $userIds)
            ->selectRaw('assigned_to, COUNT(*) as count, COALESCE(SUM(total_price), 0) as value')
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        $rows = User::whereIn('id', $userIds)->orderBy('name')->get();

        $filename = 'csr_stock_summary_'.date('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($rows, $stockCounts, $orderCounts) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['CSR Name', 'Phone', 'Stock Units', 'Completed Orders', 'Completed Value (₦)']);

            foreach ($rows as $user) {
                fputcsv($handle, [
                    $user->name,
                    $user->phone ?? '-',
                    (int) ($stockCounts->get($user->id) ?? 0),
                    (int) ($orderCounts->get($user->id)?->count ?? 0),
                    (float) ($orderCounts->get($user->id)?->value ?? 0),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function csrIds(): Collection
    {
        return User::query()
            ->where('role', 'community_sales_representative')
            ->where('is_active', true)
            ->pluck('id');
    }
}
