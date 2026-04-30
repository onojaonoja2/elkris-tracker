<?php

namespace App\Filament\Resources\StockistTransactions;

use App\Filament\Resources\StockistTransactions\Pages\ListStockistTransactions;
use App\Models\StockistTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockistTransactionResource extends Resource
{
    protected static ?string $model = StockistTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowPathRoundedSquare;

    protected static ?string $navigationLabel = 'Stock History';

    protected static ?string $modelLabel = 'Stock History';

    protected static ?string $slug = 'stock-history';

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'supervisor']);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        if ($user->role === 'supervisor') {
            return $query->whereHas('stockist', function ($q) use ($user) {
                $q->where('supervisor_id', $user->id);
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            //
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('stockist.name')
                    ->label('Stockist')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'received' => 'success',
                        'deducted' => 'danger',
                        'manual' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'received' => 'Received',
                        'deducted' => 'Deducted',
                        'manual' => 'Manual Adjustment',
                        default => $state,
                    }),
                TextColumn::make('amount')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(50),
                TextColumn::make('fieldAgent.name')
                    ->label('Field Agent')
                    ->searchable()
                    ->visible(fn ($record) => $record?->fieldAgent !== null),
                TextColumn::make('transaction_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ]);
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
            'index' => ListStockistTransactions::route('/'),
        ];
    }
}
