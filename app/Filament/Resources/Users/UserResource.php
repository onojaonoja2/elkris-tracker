<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Users';

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'supervisor', 'general_manager', 'general_accountant', 'manager']);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->role === 'supervisor') {
            return $query->whereIn('role', ['field_agent', 'community_sales_representative', 'open_market', 'retail_market']);
        }

        if ($user->role === 'manager') {
            return $query->whereIn('role', ['community_sales_representative', 'open_market', 'retail_market']);
        }

        if ($user->role === 'general_accountant') {
            return $query->whereIn('role', ['accountant', 'warehouse_manager']);
        }

        if ($user->role === 'general_manager') {
            return $query->whereIn('role', ['admin', 'supervisor', 'lead', 'rep', 'community_sales_representative', 'open_market', 'retail_market', 'sales', 'manager', 'accountant', 'warehouse_manager']);
        }

        if ($user->role === 'lead') {
            $query->where('lead_id', $user->id);
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'supervisor', 'general_manager', 'general_accountant', 'manager']);
    }

    public static function canEditAny(): bool
    {
        return static::canCreate();
    }

    public static function canEditRecord($record): bool
    {
        return static::currentUserMayManageRole($record->role);
    }

    public static function canDeleteAny(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'general_manager'], true);
    }

    public static function canDeleteRecord($record): bool
    {
        // Deletion is restricted to admin/general_manager AND only for roles they can manage.
        return static::canDeleteAny() && static::currentUserMayManageRole($record->role);
    }

    /**
     * Mirrors getEloquentQuery(): whether the current user is permitted to
     * manage users of the target role. Used by canEditRecord/canDeleteRecord.
     */
    protected static function currentUserMayManageRole(string $targetRole): bool
    {
        $role = auth()->user()->role;

        return match ($role) {
            'admin', 'general_manager' => true,
            'supervisor' => in_array($targetRole, ['field_agent', 'community_sales_representative', 'open_market', 'retail_market'], true),
            'manager' => in_array($targetRole, ['community_sales_representative', 'open_market', 'retail_market'], true),
            'general_accountant' => in_array($targetRole, ['accountant', 'warehouse_manager'], true),
            default => false,
        };
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
