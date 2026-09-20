<?php

namespace App\Filament\Resources\StockTransactions;

use App\Filament\Navigation\HasRoleBasedNavigationGroup;
use App\Filament\Resources\StockTransactions\Pages\ManageStockTransactions;
use App\Models\StockTransaction;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StockTransactionResource extends Resource
{
    use HasRoleBasedNavigationGroup;

    protected static ?string $model = StockTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static array $navigationRoles = ['admin', 'manager', 'warehouse_manager'];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'sales', 'warehouse_manager']);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->hasRole('warehouse_manager')) {
            $warehouseIds = $user->managedWarehouses()->pluck('id');

            return $query->whereIn('stock_transactions.warehouse_id', $warehouseIds);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('transaction_date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->label('Date'),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'received' => 'success',
                        'disbursed' => 'warning',
                        'delivered' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('product_name')
                    ->searchable()
                    ->sortable()
                    ->label('Product'),
                TextColumn::make('grammage')
                    ->formatStateUsing(fn ($state) => $state.'g')
                    ->sortable()
                    ->label('Size'),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('disbursed_to')
                    ->searchable()
                    ->label('Recipient / Notes'),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('product_name')
                    ->label('Product')
                    ->options([
                        'Elkris Oat Flour' => 'Elkris Oat Flour',
                        'Elkris Plantain' => 'Elkris Plantain',
                        'Elkris Poundo Yam' => 'Elkris Poundo Yam',
                    ]),
                SelectFilter::make('grammage')
                    ->label('Grammage')
                    ->options([
                        '5000' => '5000g',
                        '1800' => '1800g',
                        '1300' => '1300g',
                        '900' => '900g',
                        '650' => '650g',
                    ]),
                Filter::make('transaction_date')
                    ->form([
                        DatePicker::make('created_from')->label('From Date')->closeOnDateSelection(),
                        DatePicker::make('created_until')->label('To Date')->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (?Model $record): string => $record ? "Stock Transaction #{$record->id}" : 'Stock Transaction')
                    ->modalWidth('3xl')
                    ->infolist(fn (StockTransaction $record): array => [
                        Fieldset::make('Transaction Details')
                            ->columns(3)
                            ->schema([
                                TextEntry::make('product_name')->label('Product'),
                                TextEntry::make('grammage')->label('Weight')->formatStateUsing(fn ($state): string => $state.'g'),
                                TextEntry::make('quantity')->label('Quantity'),
                                TextEntry::make('type')
                                    ->label('Type')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'received' => 'success',
                                        'disbursed' => 'warning',
                                        'delivered' => 'info',
                                        default => 'gray',
                                    }),
                                TextEntry::make('transaction_date')->label('Date')->date('d/m/Y'),
                                TextEntry::make('disbursed_to')->label('Recipient / Notes')->placeholder('N/A'),
                                TextEntry::make('warehouse.name')->label('Warehouse')->default('N/A'),
                                TextEntry::make('user.name')->label('Recorded By')->default('N/A'),
                            ]),
                    ]),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStockTransactions::route('/'),
        ];
    }
}
