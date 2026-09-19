<?php

namespace App\Filament\Resources\WarehouseReturns;

use App\Filament\Resources\WarehouseReturns\Pages\ListWarehouseReturns;
use App\Filament\Resources\WarehouseReturns\Tables\WarehouseReturnsTable;
use App\Models\WarehouseReturn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WarehouseReturnResource extends Resource
{
    protected static ?string $model = WarehouseReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Warehouse Returns';

    protected static array $navigationRoles = ['admin', 'manager', 'warehouse_manager'];

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'warehouse_manager']);
    }

    public static function table(Table $table): Table
    {
        return WarehouseReturnsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouseReturns::route('/'),
        ];
    }
}
