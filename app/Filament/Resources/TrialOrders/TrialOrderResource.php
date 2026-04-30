<?php

namespace App\Filament\Resources\TrialOrders;

use App\Filament\Resources\TrialOrders\Pages\CreateTrialOrder;
use App\Filament\Resources\TrialOrders\Pages\EditTrialOrder;
use App\Filament\Resources\TrialOrders\Pages\ListTrialOrders;
use App\Filament\Resources\TrialOrders\Schemas\TrialOrderForm;
use App\Filament\Resources\TrialOrders\Tables\TrialOrdersTable;
use App\Models\TrialOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrialOrderResource extends Resource
{
    protected static ?string $model = TrialOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = true;

    public static function form(Schema $schema): Schema
    {
        return TrialOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrialOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()->role, ['supervisor', 'sales', 'admin']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->role === 'field_agent';
    }

    public static function canEditAny(): bool
    {
        return in_array(auth()->user()->role, ['supervisor', 'admin']);
    }

    public static function canEditRecord(TrialOrder $record): bool
    {
        // Prevent editing locked (completed) trial orders
        return ! $record->isLocked() && self::canEditAny();
    }

    public static function canDeleteAny(): bool
    {
        return in_array(auth()->user()->role, ['supervisor', 'admin']);
    }

    public static function canDeleteRecord(TrialOrder $record): bool
    {
        // Prevent deletion of locked (completed) trial orders
        return ! $record->isLocked() && self::canDeleteAny();
    }

    public static function canViewRecord(TrialOrder $record): bool
    {
        // Allow viewing all trial orders, but locked ones are read-only
        return in_array(auth()->user()->role, ['supervisor', 'sales', 'admin', 'field_agent']);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // Field agents strictly only see their own requested or approved loads
        if ($user->role === 'field_agent') {
            $query->where('agent_id', $user->id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrialOrders::route('/'),
            'create' => CreateTrialOrder::route('/create'),
            'edit' => EditTrialOrder::route('/{record}/edit'),
        ];
    }
}
