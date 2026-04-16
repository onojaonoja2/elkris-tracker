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

class TrialOrderResource extends Resource
{
    protected static ?string $model = TrialOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
        return in_array(auth()->user()->role, ['field_agent', 'supervisor', 'sales', 'admin']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->role === 'field_agent';
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
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
