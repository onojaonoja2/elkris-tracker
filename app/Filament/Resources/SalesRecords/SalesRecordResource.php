<?php

namespace App\Filament\Resources\SalesRecords;

use App\Filament\Resources\SalesRecords\Pages\CreateSalesRecord;
use App\Filament\Resources\SalesRecords\Pages\EditSalesRecord;
use App\Filament\Resources\SalesRecords\Pages\ListSalesRecords;
use App\Filament\Resources\SalesRecords\Schemas\SalesRecordForm;
use App\Filament\Resources\SalesRecords\Tables\SalesRecordsTable;
use App\Filament\Traits\HasViewModal;
use App\Models\SalesRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SalesRecordResource extends Resource
{
    use HasViewModal;

    protected static ?string $model = SalesRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static bool $shouldRegisterNavigation = true;

    public static function form(Schema $schema): Schema
    {
        return SalesRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesRecordsTable::configure($table);
    }

    protected static function getViewRelations(): array
    {
        return [];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole([
            'open_market', 'retail_market', 'community_sales_representative',
            'supervisor', 'accountant', 'admin', 'manager',
        ]);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasAnyRole(['open_market', 'retail_market', 'community_sales_representative', 'admin']);
    }

    public static function canEditAny(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    public static function canEditRecord(SalesRecord $record): bool
    {
        return ! $record->isLocked() && self::canEditAny();
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    public static function canDeleteRecord(SalesRecord $record): bool
    {
        return ! $record->isLocked() && self::canDeleteAny();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (auth()->user()->hasAnyRole(['open_market', 'retail_market', 'community_sales_representative'])) {
            $query->where('agent_id', $user->id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesRecords::route('/'),
            'create' => CreateSalesRecord::route('/create'),
            'edit' => EditSalesRecord::route('/{record}/edit'),
        ];
    }
}
