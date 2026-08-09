<?php

namespace App\Filament\Widgets;

use App\Models\StockTransfer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingStockRequests extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole([
            'admin', 'supervisor', 'warehouse_manager',
            'accountant', 'sales', 'manager', 'community_sales_representative',
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $user = auth()->user();
                $query = StockTransfer::whereIn('status', ['requested', 'approved']);

                if ($user->hasRole('supervisor')) {
                    $query->where(function (Builder $q) use ($user) {
                        $q->whereHas('toAgent', fn (Builder $sq) => $sq->where('role', 'community_sales_representative'))
                            ->orWhere('requested_by', $user->id);
                    });
                }

                if ($user->hasRole('warehouse_manager')) {
                    $warehouseIds = $user->managedWarehouses()->pluck('id');
                    $query->whereIn('from_warehouse_id', $warehouseIds);
                }

                if ($user->hasRole('sales')) {
                    $warehouseIds = $user->salesWarehouses()->pluck('id');
                    $query->whereIn('from_warehouse_id', $warehouseIds);
                }

                if ($user->hasRole('community_sales_representative')) {
                    $query->where(function (Builder $q) use ($user) {
                        $q->where('to_agent_id', $user->id)
                            ->orWhere('requested_by', $user->id);
                    });
                }

                return $query;
            })
            ->columns([
                TextColumn::make('id')
                    ->label('Request #'),
                TextColumn::make('fromWarehouse.name')
                    ->label('From'),
                TextColumn::make('toAgent.name')
                    ->label('For Agent'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('requester.name')
                    ->label('Requested By'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
