<?php

namespace App\Filament\Resources\Stockists;

use App\Filament\Resources\Stockists\Pages\CreateStockist;
use App\Filament\Resources\Stockists\Pages\ListStockists;
use App\Filament\Resources\Stockists\Schemas\StockistForm;
use App\Filament\Resources\Stockists\Tables\StockistTable;
use App\Models\Stockist;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockistResource extends Resource
{
    protected static ?string $model = Stockist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingStorefront;

    protected static ?string $navigationLabel = 'Stockists';

    protected static ?string $modelLabel = 'Stockist';

    protected static ?string $slug = 'stockists';

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'supervisor']);
    }

    public static function canCreate(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'supervisor']);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        if ($user->role === 'supervisor') {
            return $query->where('supervisor_id', $user->id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return StockistForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockistTable::configure($table);
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
            'index' => ListStockists::route('/'),
            'create' => CreateStockist::route('/create'),
        ];
    }
}
