<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Order;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
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

    public static function shouldRegisterNavigation(): bool
    {
        return ! in_array(auth()->user()->role, ['manager', 'admin']);
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
                DatePicker::make('expected_delivery_date')
                    ->label('Expected Delivery Date')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
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
                TextColumn::make('expected_delivery_date')
                    ->label('Expected Delivery')
                    ->date('d/m/Y')
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
                Filter::make('order_type')
                    ->label('Order Type')
                    ->form([
                        Select::make('order_type')
                            ->options([
                                'one_time' => 'One-Time Order',
                                'repeat' => 'Repeat Order (2+)',
                            ])->placeholder('All'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['order_type'])) {
                            return $query;
                        }

                        return $query->whereHas('customer', function ($q) use ($data) {
                            $subQuery = Order::select('customer_id')
                                ->whereNotNull('customer_id')
                                ->groupBy('customer_id')
                                ->selectRaw('customer_id, COUNT(*) as order_count');

                            if ($data['order_type'] === 'one_time') {
                                $subQuery->having('order_count', 1);
                            } else {
                                $subQuery->having('order_count', '>', 1);
                            }

                            $q->whereIn('id', $subQuery->pluck('customer_id'));
                        });
                    }),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->closeOnDateSelection(),
                        DatePicker::make('created_until')->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                Action::make('view_customer')
                    ->label('View Customer')
                    ->icon('heroicon-o-user')
                    ->color('info')
                    ->modalHeading('Customer Information')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->infolist(function (Order $record) {
                        $entries = [
                            TextEntry::make('customer.customer_name')->label('Name'),
                            TextEntry::make('customer.phone_number')->label('Phone'),
                            TextEntry::make('customer.address')->label('Address'),
                            TextEntry::make('customer.city')->label('City'),
                            TextEntry::make('customer.state')->label('State'),
                            TextEntry::make('customer.diabetic_awareness')->label('Diabetic Awareness'),
                        ];

                        foreach ($record->products as $product) {
                            $entries[] = TextEntry::make("product_{$product->id}_name")
                                ->label('Product')
                                ->default($product->product_name);
                            $entries[] = TextEntry::make("product_{$product->id}_grammage")
                                ->label('Grammage')
                                ->default($product->grammage.'g');
                            $entries[] = TextEntry::make("product_{$product->id}_size")
                                ->label('Size')
                                ->default($product->size ?? 'N/A');
                            $entries[] = TextEntry::make("product_{$product->id}_price")
                                ->label('Unit Price')
                                ->money('NGN')
                                ->default($product->price);
                            $entries[] = TextEntry::make("product_{$product->id}_qty")
                                ->label('Paid Qty')
                                ->default($product->quantity);
                            if ($product->free_quantity > 0) {
                                $entries[] = TextEntry::make("product_{$product->id}_free")
                                    ->label('Free Qty')
                                    ->default($product->free_quantity)
                                    ->color('success');
                                $entries[] = TextEntry::make("product_{$product->id}_total")
                                    ->label('Total Qty')
                                    ->default($product->quantity + $product->free_quantity);
                            }
                            if ($product->promotion_type) {
                                $entries[] = TextEntry::make("product_{$product->id}_promo")
                                    ->label('Promotion')
                                    ->default(ucwords(str_replace('_', ' ', $product->promotion_type)))
                                    ->badge()
                                    ->color('info');
                            }
                        }

                        return $entries;
                    }),
                EditAction::make()->visible(fn () => in_array(auth()->user()->role, ['admin', 'sales'])),
            ])
            ->toolbarActions([
                Action::make('export')
                    ->label('Export Orders')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $user = auth()->user();
                        $query = Order::query()->with(['customer', 'user', 'products']);

                        if (! in_array($user->role, ['admin', 'sales'])) {
                            $query->where('user_id', $user->id);
                        }

                        $orders = $query->orderBy('created_at', 'desc')->get();
                        $data = [];
                        foreach ($orders as $order) {
                            $products = $order->products->map(fn ($p) => "{$p->product_name} ({$p->grammage}g) x{$p->quantity}")->implode(', ');
                            $data[] = [
                                $order->id,
                                $order->customer?->customer_name ?? 'N/A',
                                $order->user?->name ?? 'N/A',
                                ucfirst($order->status),
                                $products,
                                number_format($order->total_price, 2),
                                $order->created_at->format('d/m/Y H:i'),
                                $order->expected_delivery_date ? Carbon::parse($order->expected_delivery_date)->format('d/m/Y') : 'N/A',
                            ];
                        }

                        return response()->streamDownload(function () use ($data) {
                            $file = fopen('php://output', 'w');
                            fputcsv($file, ['Order ID', 'Customer', 'Submitted By', 'Status', 'Products', 'Total Price', 'Submitted Date', 'Expected Delivery']);
                            foreach ($data as $row) {
                                fputcsv($file, $row);
                            }
                            fclose($file);
                        }, 'orders_export_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment',
                        ]);
                    }),
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
