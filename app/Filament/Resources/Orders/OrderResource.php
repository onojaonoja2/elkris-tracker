<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Order;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'Orders';

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'sales', 'rep', 'lead', 'manager']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'dispatched' => 'Dispatched',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Order ID')->searchable(),
                TextColumn::make('customer.customer_name')->label('Customer')->searchable(),
                TextColumn::make('user.name')->label('Submitted By'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'dispatched' => 'info',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('total_price')
                    ->money('NGN'),
                TextColumn::make('created_at')
                    ->label('Submitted Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'dispatched' => 'Dispatched',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ]),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from'),
                        DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make()->visible(fn () => in_array(auth()->user()->role, ['admin', 'sales'])),
            ])
            ->toolbarActions([
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        if (in_array($user->role, ['admin', 'sales'])) {
            return parent::getEloquentQuery();
        }

        // Reps/Leads see only theirs
        return parent::getEloquentQuery()->where('user_id', $user->id);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrders::route('/'),
        ];
    }
}
