<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Exports\OrderExporter;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class LeadOrdersWidget extends TableWidget
{
    protected static ?string $heading = 'Team Orders';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasRole('lead');
    }

    public function table(Table $table): Table
    {
        $leadId = auth()->id();
        $repIds = User::where('lead_id', $leadId)->where('role', 'rep')->pluck('id')->toArray();
        $allUserIds = array_merge([$leadId], $repIds);

        return $table
            ->query(function () use ($allUserIds): Builder {
                return Order::query()
                    ->whereIn('user_id', $allUserIds)
                    ->with(['user', 'customer']);
            })
            ->columns([
                TextColumn::make('id')->label('Order ID')->searchable(),
                TextColumn::make('customer.customer_name')->label('Customer')->searchable(),
                TextColumn::make('user.name')
                    ->label('Submitted By')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (OrderStatus $state): string => $state->color()),
                TextColumn::make('total_price')
                    ->label('Order Value')
                    ->money('NGN'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date('d/m/Y'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('From Date')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                        DatePicker::make('created_until')
                            ->label('To Date')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
                Filter::make('rep_filter')
                    ->label('Filter by Rep')
                    ->form([
                        Select::make('user_id')
                            ->label('Select User')
                            ->options(fn () => User::whereIn('id', array_merge([auth()->id()], User::where('lead_id', auth()->id())->where('role', 'rep')->pluck('id')->toArray()))->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('All Users'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['user_id'], fn ($q) => $q->where('user_id', $data['user_id']));
                    }),
                Filter::make('status')
                    ->label('Status')
                    ->form([
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'dispatched' => 'Dispatched',
                                'delivered' => 'Delivered',
                                'cancelled' => 'Cancelled',
                            ])
                            ->placeholder('All'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['status'], fn ($q) => $q->where('status', $data['status']));
                    }),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(OrderExporter::class),
            ])
            ->paginated([10, 25, 50]);
    }
}
