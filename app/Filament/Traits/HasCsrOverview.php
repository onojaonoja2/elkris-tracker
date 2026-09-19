<?php

namespace App\Filament\Traits;

use App\Enums\OrderStatus;
use App\Models\AgentStock;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\User;
use App\Support\DashboardDateScope;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared logic for dashboards that show a per-CSR overview (stock, sales,
 * credit sales and order aggregates) alongside an Excel export.
 */
trait HasCsrOverview
{
    /**
     * @return array{0: string, 1: string}
     */
    protected function appliedPeriod(): array
    {
        [$from, $to] = DashboardDateScope::fromSession();

        return [$from->toDateTimeString(), $to->toDateTimeString()];
    }

    /**
     * @return array{
     *     stock: Collection,
     *     sales: Collection,
     *     completedOrders: Collection,
     *     pendingOrders: Collection,
     *     creditSales: Collection,
     * }
     */
    public function csrStats(?Collection $csrIds = null): array
    {
        [$from, $to] = $this->appliedPeriod();

        $csrIds = $csrIds ?? User::where('role', 'community_sales_representative')->pluck('id');

        $stock = AgentStock::whereIn('user_id', $csrIds)
            ->selectRaw('user_id, SUM(quantity) as total_qty')
            ->groupBy('user_id')
            ->pluck('total_qty', 'user_id');

        $sales = SalesRecord::whereIn('agent_id', $csrIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('agent_id, COUNT(*) as count, COALESCE(SUM(total_value), 0) as total_value')
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');

        $completedOrders = Order::where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('assigned_to', $csrIds)
            ->where('status', OrderStatus::Delivered)
            ->selectRaw('assigned_to, COUNT(*) as order_count, COALESCE(SUM(total_price), 0) as order_value')
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        $pendingOrders = Order::where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('assigned_to', $csrIds)
            ->pendingDelivery()
            ->selectRaw('assigned_to, COUNT(*) as order_count, COALESCE(SUM(total_price), 0) as order_value')
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        $creditSales = SalesRecord::outstanding()
            ->whereIn('agent_id', $csrIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('agent_id, COALESCE(SUM(total_value), 0) as total_value')
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');

        return compact('stock', 'sales', 'completedOrders', 'pendingOrders', 'creditSales');
    }

    /**
     * @return array<int, TextColumn>
     */
    protected function csrOverviewColumns(array $stats): array
    {
        return [
            TextColumn::make('name')
                ->label('CSR Name')
                ->searchable()
                ->sortable(),

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
                ->getStateUsing(fn (User $record): int => $stats['stock']->get($record->id, 0))
                ->sortable(),

            TextColumn::make('sales_count')
                ->label('Sales Count')
                ->getStateUsing(fn (User $record): int => $stats['sales']->get($record->id)?->count ?? 0)
                ->sortable(),

            TextColumn::make('sales_value')
                ->label('Sales Value')
                ->money('NGN')
                ->getStateUsing(fn (User $record): float => (float) ($stats['sales']->get($record->id)?->total_value ?? 0))
                ->sortable(),

            TextColumn::make('completed_orders')
                ->label('Completed Orders')
                ->getStateUsing(fn (User $record): int => (int) ($stats['completedOrders']->get($record->id)?->order_count ?? 0)),

            TextColumn::make('completed_value')
                ->label('Completed Value')
                ->money('NGN')
                ->getStateUsing(fn (User $record): float => (float) ($stats['completedOrders']->get($record->id)?->order_value ?? 0)),

            TextColumn::make('pending_orders')
                ->label('Pending Orders')
                ->getStateUsing(fn (User $record): int => (int) ($stats['pendingOrders']->get($record->id)?->order_count ?? 0)),

            TextColumn::make('pending_value')
                ->label('Pending Value')
                ->money('NGN')
                ->getStateUsing(fn (User $record): float => (float) ($stats['pendingOrders']->get($record->id)?->order_value ?? 0)),

            TextColumn::make('credit_sales_value')
                ->label('Credit Sales Value')
                ->money('NGN')
                ->getStateUsing(fn (User $record): float => (float) ($stats['creditSales']->get($record->id)?->total_value ?? 0)),
        ];
    }

    /**
     * @return array<int, Fieldset>
     */
    protected function userBreakdown(User $record): array
    {
        [$from, $to] = $this->appliedPeriod();

        $stats = $this->csrStats(collect([$record->id]));
        $sales = $stats['sales']->get($record->id);
        $credit = $stats['creditSales']->get($record->id);
        $completed = $stats['completedOrders']->get($record->id);
        $pending = $stats['pendingOrders']->get($record->id);

        return [
            Fieldset::make('Agent Details')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Name'),
                    TextEntry::make('phone')->label('Phone')->placeholder('N/A'),
                    TextEntry::make('lga.name')->label('LGA')->default('N/A'),
                    TextEntry::make('state.name')->label('State')->default('N/A'),
                    TextEntry::make('is_active')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Suspended')
                        ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                    TextEntry::make('stock_units')
                        ->label('Stock Units')
                        ->state(fn (): int => (int) $stats['stock']->get($record->id, 0)),
                    TextEntry::make('created_at')->label('Joined')->dateTime(),
                ]),
            Fieldset::make('Order Summary')
                ->columns(3)
                ->schema([
                    TextEntry::make('period')
                        ->label('Period')
                        ->state(fn (): string => $this->formatPeriod($from, $to)),
                    TextEntry::make('completed_orders')
                        ->label('Completed Orders')
                        ->state(fn (): int => (int) ($completed->order_count ?? 0)),
                    TextEntry::make('completed_value')
                        ->label('Completed Value')
                        ->money('NGN')
                        ->state(fn (): float => (float) ($completed->order_value ?? 0)),
                    TextEntry::make('pending_orders')
                        ->label('Pending Orders')
                        ->state(fn (): int => (int) ($pending->order_count ?? 0)),
                    TextEntry::make('pending_value')
                        ->label('Pending Value')
                        ->money('NGN')
                        ->state(fn (): float => (float) ($pending->order_value ?? 0)),
                ]),
            Fieldset::make('Sales Summary')
                ->columns(3)
                ->schema([
                    TextEntry::make('sales_count')
                        ->label('Sales Count')
                        ->state(fn (): int => (int) ($sales->count ?? 0)),
                    TextEntry::make('sales_value')
                        ->label('Sales Value')
                        ->money('NGN')
                        ->state(fn (): float => (float) ($sales->total_value ?? 0)),
                    TextEntry::make('credit_sales_value')
                        ->label('Credit Sales Value')
                        ->money('NGN')
                        ->state(fn (): float => (float) ($credit->total_value ?? 0)),
                ]),
        ];
    }

    public function exportXlsx(): StreamedResponse
    {
        $records = $this->getFilteredTableQuery()->get();
        $stats = $this->csrStats($records->pluck('id'));

        return response()->streamDownload(function () use ($records, $stats) {
            $path = tempnam(sys_get_temp_dir(), 'csr_').'.xlsx';

            $this->buildExcelFile($path, $records, $stats);

            readfile($path);
            unlink($path);
        }, 'csr_overview_'.Carbon::now()->format('Y_m_d_H_i_s').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function buildExcelFile(string $path, Collection $records, array $stats): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([
            'CSR Name', 'LGA', 'State', 'Status', 'Stock Units',
            'Sales Count', 'Sales Value', 'Credit Sales Value',
            'Completed Orders', 'Completed Value', 'Pending Orders', 'Pending Value',
        ]));

        foreach ($records as $record) {
            $sales = $stats['sales']->get($record->id);
            $credit = $stats['creditSales']->get($record->id);
            $completed = $stats['completedOrders']->get($record->id);
            $pending = $stats['pendingOrders']->get($record->id);

            $writer->addRow(Row::fromValues([
                $record->name,
                $record->lga?->name ?? 'N/A',
                $record->state?->name ?? 'N/A',
                $record->is_active ? 'Active' : 'Suspended',
                (int) $stats['stock']->get($record->id, 0),
                (int) ($sales->count ?? 0),
                (float) ($sales->total_value ?? 0),
                (float) ($credit->total_value ?? 0),
                (int) ($completed->order_count ?? 0),
                (float) ($completed->order_value ?? 0),
                (int) ($pending->order_count ?? 0),
                (float) ($pending->order_value ?? 0),
            ]));
        }

        $writer->close();
    }

    private function formatPeriod(string $from, string $to): string
    {
        return Carbon::parse($from)->format('d M Y').' - '.Carbon::parse($to)->format('d M Y');
    }
}
