<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class SupervisorCreditSalesWidget extends TableWidget
{
    protected static ?string $heading = 'Credit Sales by CSR';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->role === 'supervisor';
    }

    public function table(Table $table): Table
    {
        $from = Session::get('supervisor_date_from', now()->startOfDay()->toDateTimeString());
        $to = Session::get('supervisor_date_to', now()->endOfDay()->toDateTimeString());

        $csrIds = User::where('role', 'community_sales_representative')->pluck('id');

        $creditData = SalesRecord::whereIn('agent_id', $csrIds)
            ->where('is_credit', true)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$from, $to])
            ->with('agent')
            ->get()
            ->groupBy('agent_id')
            ->map(function ($records, $agentId) {
                $agent = User::find($agentId);
                $pending = $records->where('credit_status', 'pending_payment');
                $collected = $records->where('credit_status', 'collected');
                $overdue = $pending->filter(fn ($r) => $r->expected_collection_date && $r->expected_collection_date->isPast());

                return [
                    'agent_name' => $agent?->name ?? 'Unknown',
                    'total_credit_value' => $records->sum('total_value'),
                    'pending_count' => $pending->count(),
                    'pending_value' => $pending->sum('total_value'),
                    'collected_count' => $collected->count(),
                    'collected_value' => $collected->sum('total_value'),
                    'overdue_count' => $overdue->count(),
                    'overdue_value' => $overdue->sum('total_value'),
                ];
            })
            ->values();

        return $table
            ->query(fn () => User::whereIn('id', $csrIds)->whereRaw('1=0'))
            ->columns([
                TextColumn::make('agent_name')
                    ->label('CSR')
                    ->searchable(),
                TextColumn::make('total_credit_value')
                    ->label('Total Credit (₦)')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('pending_count')
                    ->label('Pending')
                    ->sortable(),
                TextColumn::make('pending_value')
                    ->label('Pending Value (₦)')
                    ->money('NGN'),
                TextColumn::make('collected_count')
                    ->label('Collected')
                    ->sortable(),
                TextColumn::make('collected_value')
                    ->label('Collected Value (₦)')
                    ->money('NGN'),
                TextColumn::make('overdue_count')
                    ->label('Overdue')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('overdue_value')
                    ->label('Overdue Value (₦)')
                    ->money('NGN'),
            ])
            ->record(fn () => $creditData->toArray())
            ->defaultSort('total_credit_value', 'desc')
            ->paginated(false);
    }
}
