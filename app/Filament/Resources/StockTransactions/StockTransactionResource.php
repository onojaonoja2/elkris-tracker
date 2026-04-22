<?php

namespace App\Filament\Resources\StockTransactions;

use App\Filament\Resources\StockTransactions\Pages\ManageStockTransactions;
use App\Models\StockTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockTransactionResource extends Resource
{
    protected static ?string $model = StockTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static \UnitEnum|string|null $navigationGroup = 'Inventory';

    public static function canCreate(): bool
    {
        return false;
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
            ->defaultSort('transaction_date', 'desc')
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
                    ->formatStateUsing(fn ($state) => $state . 'g')
                    ->sortable()
                    ->label('Size'),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('disbursed_to')
                    ->searchable()
                    ->label('Recipient / Notes'),
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
                \Filament\Tables\Filters\Filter::make('transaction_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('created_until')->label('To Date'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('transaction_date', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('transaction_date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStockTransactions::route('/'),
        ];
    }
}
