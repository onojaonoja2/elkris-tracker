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
        return in_array(auth()->user()->role, [
            'admin', 'supervisor', 'warehouse_manager',
            'accountant', 'sales', 'manager',
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $user = auth()->user();
                $query = StockTransfer::whereIn('status', ['requested', 'approved']);

                if ($user->role === 'supervisor') {
                    $query->where(function (Builder $q) use ($user) {
                        $q->whereHas('toStockist', fn (Builder $sq) => $sq->where('supervisor_id', $user->id))
                            ->orWhere('requested_by', $user->id);
                    });
                }

                if ($user->role === 'warehouse_manager') {
                    $warehouseIds = $user->managedWarehouses()->pluck('id');
                    $query->whereIn('from_warehouse_id', $warehouseIds);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('id')
                    ->label('Request #'),
                TextColumn::make('fromWarehouse.name')
                    ->label('From'),
                TextColumn::make('toStockist.name')
                    ->label('For Stockist'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'requested' => 'info',
                        'approved' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('requester.name')
                    ->label('Requested By'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
