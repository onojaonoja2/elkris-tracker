<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use App\Models\State;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class ManagerSalesRecordsByStateWidget extends TableWidget
{
    protected static ?string $heading = 'Sales Records by State';

    protected int|string|array $columnSpan = 6;

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'manager', 'general_manager']);
    }

    public function table(Table $table): Table
    {
        $aggregates = SalesRecord::select(
            DB::raw('lga_state.name as state_name'),
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN status = 'receipt_uploaded' THEN 1 ELSE 0 END) as pending"),
            DB::raw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved"),
            DB::raw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected"),
            DB::raw('COALESCE(SUM(total_value), 0) as total_value'),
        )
            ->leftJoin('users', 'sales_records.agent_id', '=', 'users.id')
            ->leftJoin('lgas', 'users.lga_id', '=', 'lgas.id')
            ->leftJoin('states as lga_state', 'lgas.state_id', '=', 'lga_state.id')
            ->groupBy('lga_state.name')
            ->orderBy('state_name')
            ->get()
            ->keyBy('state_name');

        return $table
            ->query(fn (): Builder => State::query()->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->label('State')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->name)?->total ?? 0)
                    ->numeric()
                    ->sortable(),
                TextColumn::make('pending')
                    ->label('Pending')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->name)?->pending ?? 0)
                    ->numeric()
                    ->color('warning'),
                TextColumn::make('approved')
                    ->label('Approved')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->name)?->approved ?? 0)
                    ->numeric()
                    ->color('success'),
                TextColumn::make('rejected')
                    ->label('Rejected')
                    ->getStateUsing(fn ($record): int => $aggregates->get($record->name)?->rejected ?? 0)
                    ->numeric()
                    ->color('danger'),
                TextColumn::make('total_value')
                    ->label('Total Value (₦)')
                    ->getStateUsing(fn ($record): float => $aggregates->get($record->name)?->total_value ?? 0)
                    ->money('NGN')
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
