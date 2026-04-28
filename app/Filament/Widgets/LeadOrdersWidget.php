<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LeadOrdersWidget extends TableWidget
{
    protected static ?string $heading = 'Team Orders';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->role === 'lead';
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
                BadgeColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'dispatched' => 'info',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('total_price')
                    ->label('Order Value')
                    ->money('NGN'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date('d/m/Y'),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('From Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('created_until')
                            ->label('To Date')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
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
                Action::make('export')
                    ->label('Export Orders')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function () {
                        $leadId = auth()->id();
                        $repIds = User::where('lead_id', $leadId)->where('role', 'rep')->pluck('id')->toArray();
                        $allUserIds = array_merge([$leadId], $repIds);

                        $orders = Order::query()
                            ->whereIn('user_id', $allUserIds)
                            ->with(['user', 'customer'])
                            ->orderBy('created_at', 'desc')
                            ->get();

                        $data = [];
                        foreach ($orders as $order) {
                            $data[] = [
                                $order->id,
                                $order->customer?->customer_name ?? 'N/A',
                                $order->user?->name ?? 'N/A',
                                ucfirst($order->status),
                                number_format($order->total_price, 2),
                                $order->created_at->format('d/m/Y H:i'),
                            ];
                        }

                        return response()->streamDownload(function () use ($data) {
                            $file = fopen('php://output', 'w');
                            fputcsv($file, ['Order ID', 'Customer', 'Submitted By', 'Status', 'Total Price', 'Date']);
                            foreach ($data as $row) {
                                fputcsv($file, $row);
                            }
                            fclose($file);
                        }, 'team_orders_export_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv', [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment',
                        ]);
                    }),
            ])
            ->paginated(false);
    }
}
