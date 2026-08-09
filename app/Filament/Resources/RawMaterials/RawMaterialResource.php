<?php

namespace App\Filament\Resources\RawMaterials;

use App\Filament\Navigation\HasRoleBasedNavigationGroup;
use App\Filament\Resources\RawMaterials\Pages\ManageRawMaterials;
use App\Filament\Resources\RawMaterials\Schemas\RawMaterialForm;
use App\Filament\Resources\RawMaterials\Tables\RawMaterialsTable;
use App\Models\RawMaterial;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RawMaterialResource extends Resource
{
    use HasRoleBasedNavigationGroup;

    protected static ?string $model = RawMaterial::class;

    protected static ?string $navigationRole = 'production_management';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    public static function form(Schema $schema): Schema
    {
        return RawMaterialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RawMaterialsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRawMaterials::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([
            'admin',
            'production_management',
            'general_manager',
            'manager',
            'accountant',
            'general_accountant',
        ]) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'production_management']) ?? false;
    }

    public static function canEditAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'production_management']) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'production_management']) ?? false;
    }
}
