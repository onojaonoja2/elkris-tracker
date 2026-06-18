<?php

namespace App\Filament\Resources\StockTransfers;

use App\Filament\Resources\StockTransfers\Pages\CreateStockTransfer;
use App\Filament\Resources\StockTransfers\Pages\EditStockTransfer;
use App\Filament\Resources\StockTransfers\Pages\ListStockTransfers;
use App\Filament\Resources\StockTransfers\Schemas\StockTransferForm;
use App\Filament\Resources\StockTransfers\Tables\StockTransfersTable;
use App\Models\StockTransfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $count = StockTransfer::whereIn('status', ['requested', 'approved']);

        if ($user->role === 'accountant') {
            $count->where('status', 'requested');
        }

        if ($user->role === 'warehouse_manager') {
            $warehouseIds = $user->managedWarehouses()->pluck('id');
            $count->whereIn('from_warehouse_id', $warehouseIds);
        }

        if ($user->role === 'sales') {
            $warehouseIds = $user->salesWarehouses()->pluck('id');
            $count->whereIn('from_warehouse_id', $warehouseIds);
        }

        if ($user->role === 'community_sales_representative') {
            $count->where(function (Builder $q) use ($user) {
                $q->where('to_agent_id', $user->id)
                    ->orWhere('requested_by', $user->id);
            });
        }

        return (string) $count->count();
    }

    public static function form(Schema $schema): Schema
    {
        return StockTransferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockTransfersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()->role, [
            'admin', 'warehouse_manager', 'manager', 'supervisor',
            'accountant', 'sales', 'community_sales_representative', 'open_market',
            'retail_market',
        ]);
    }

    public static function canCreate(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'warehouse_manager', 'supervisor', 'community_sales_representative', 'open_market', 'retail_market']);
    }

    public static function canEditAny(): bool
    {
        return auth()->user()->role === 'admin';
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->role === 'admin';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->role === 'warehouse_manager') {
            $warehouseIds = $user->managedWarehouses()->pluck('id');
            $query->where(function (Builder $q) use ($warehouseIds) {
                $q->whereIn('from_warehouse_id', $warehouseIds)
                    ->orWhereIn('to_warehouse_id', $warehouseIds);
            });
        }

        if (in_array($user->role, ['community_sales_representative', 'open_market', 'retail_market'])) {
            $query->where('requested_by', $user->id);
        }

        if ($user->role === 'sales') {
            $warehouseIds = $user->salesWarehouses()->pluck('id');
            $query->whereIn('from_warehouse_id', $warehouseIds);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockTransfers::route('/'),
            'create' => CreateStockTransfer::route('/create'),
            'edit' => EditStockTransfer::route('/{record}/edit'),
        ];
    }
}
