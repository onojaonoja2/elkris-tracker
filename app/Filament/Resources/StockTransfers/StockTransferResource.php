<?php

namespace App\Filament\Resources\StockTransfers;

use App\Filament\Navigation\HasRoleBasedNavigationGroup;
use App\Filament\Resources\StockTransfers\Pages\CreateStockTransfer;
use App\Filament\Resources\StockTransfers\Pages\EditStockTransfer;
use App\Filament\Resources\StockTransfers\Pages\ListStockTransfers;
use App\Filament\Resources\StockTransfers\Schemas\StockTransferForm;
use App\Filament\Resources\StockTransfers\Tables\StockTransfersTable;
use App\Filament\Traits\HasViewModal;
use App\Models\StockTransfer;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockTransferResource extends Resource
{
    use HasRoleBasedNavigationGroup, HasViewModal;

    protected static ?string $model = StockTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static array $navigationRoles = ['admin', 'manager', 'warehouse_manager', 'supervisor'];

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $count = StockTransfer::whereIn('status', ['requested', 'approved', 'collected']);

        if ($user->hasRole('accountant')) {
            $count->where('status', 'requested');
        }

        if ($user->hasRole('warehouse_manager')) {
            $warehouseIds = $user->managedWarehouses()->pluck('id');
            $count->whereIn('from_warehouse_id', $warehouseIds);
        }

        if ($user->hasRole('sales')) {
            $warehouseIds = $user->salesWarehouses()->pluck('id');
            $count->whereIn('from_warehouse_id', $warehouseIds);
        }

        if ($user->hasRole('community_sales_representative')) {
            $count->where(function (Builder $q) use ($user) {
                $q->where('to_agent_id', $user->id)
                    ->orWhere('requested_by', $user->id);
            });
        }

        if ($user->hasRole('supervisor')) {
            $agentIds = User::where('lead_id', $user->id)
                ->orWhere('portfolio_agent_id', $user->id)
                ->pluck('id');
            $count->where(function (Builder $q) use ($agentIds) {
                $q->whereIn('from_agent_id', $agentIds)
                    ->orWhere('requested_by', auth()->id());
            });
        }

        if ($user->hasRole('manager')) {
            $count->where(function (Builder $q) {
                $q->where('source_type', 'agent_collection')
                    ->orWhere('requested_by', auth()->id());
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

    protected static function getViewRelations(): array
    {
        return [
            'items' => [
                'label' => 'Transfer Items',
                'columns' => ['product_type_id', 'grammage', 'quantity', 'rejected_quantity'],
            ],
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole([
            'admin', 'warehouse_manager', 'manager', 'supervisor',
            'accountant', 'sales', 'community_sales_representative', 'open_market',
            'retail_market',
        ]);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'warehouse_manager', 'supervisor', 'sales', 'community_sales_representative', 'open_market', 'retail_market']);
    }

    public static function canEditAny(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->hasRole('warehouse_manager')) {
            $warehouseIds = $user->managedWarehouses()->pluck('id');
            $query->where(function (Builder $q) use ($warehouseIds) {
                $q->whereIn('from_warehouse_id', $warehouseIds)
                    ->orWhereIn('to_warehouse_id', $warehouseIds);
            });
        }

        if (auth()->user()->hasAnyRole(['community_sales_representative', 'open_market', 'retail_market'])) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('requested_by', $user->id)
                    ->orWhere('to_agent_id', $user->id);
            });
        }

        if ($user->hasRole('sales')) {
            $warehouseIds = $user->salesWarehouses()->pluck('id');
            $query->where(function (Builder $q) use ($warehouseIds, $user) {
                $q->whereIn('from_warehouse_id', $warehouseIds)
                    ->orWhere('to_agent_id', $user->id)
                    ->orWhere('requested_by', $user->id);
            });
        }

        if ($user->hasRole('supervisor')) {
            $agentIds = User::where('lead_id', $user->id)
                ->orWhere('portfolio_agent_id', $user->id)
                ->pluck('id');
            $query->where(function (Builder $q) use ($agentIds) {
                $q->whereIn('from_agent_id', $agentIds)
                    ->orWhere('requested_by', auth()->id());
            });
        }

        if ($user->hasRole('manager')) {
            $query->where(function (Builder $q) {
                $q->where('source_type', 'agent_collection')
                    ->orWhere('requested_by', auth()->id());
            });
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
