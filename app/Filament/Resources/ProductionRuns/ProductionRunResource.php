<?php

namespace App\Filament\Resources\ProductionRuns;

use App\Filament\Navigation\HasRoleBasedNavigationGroup;
use App\Filament\Resources\ProductionRuns\Actions\ReviewProductionRunAction;
use App\Filament\Resources\ProductionRuns\Pages\ManageProductionRuns;
use App\Filament\Resources\ProductionRuns\Schemas\ProductionRunForm;
use App\Filament\Resources\ProductionRuns\Tables\ProductionRunsTable;
use App\Models\ProductionRun;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductionRunResource extends Resource
{
    use HasRoleBasedNavigationGroup;

    protected static ?string $model = ProductionRun::class;

    protected static ?string $navigationRole = 'production_management';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;

    public static function form(Schema $schema): Schema
    {
        return ProductionRunForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionRunsTable::configure($table)
            ->recordActions([
                ProductionRunResource::editAction(),
                ProductionRunResource::deleteAction(),
                ReviewProductionRunAction::make(),
            ]);
    }

    public static function editAction(): EditAction
    {
        return EditAction::make()
            ->visible(fn (ProductionRun $record): bool => ! $record->isLocked());
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'production_management']) ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProductionRuns::route('/'),
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
