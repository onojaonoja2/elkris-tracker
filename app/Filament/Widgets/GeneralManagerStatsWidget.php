<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\TrialOrderStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\SalesRecords\SalesRecordResource;
use App\Filament\Resources\TrialOrders\TrialOrderResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\TrialOrder;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class GeneralManagerStatsWidget extends BaseWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $now = Carbon::now('Africa/Lagos');
        $preset = Session::get('manager_date_preset', 'today');

        match ($preset) {
            'yesterday' => $from = $now->copy()->subDay()->startOfDay(),
            'this_week' => $from = $now->copy()->startOfWeek(),
            'this_month' => $from = $now->copy()->startOfMonth(),
            'lifetime' => $from = Carbon::now('Africa/Lagos')->subYears(10),
            default => $from = $now->copy()->setHour(8)->setMinute(0)->setSecond(0),
        };

        if ($preset !== 'lifetime') {
            if ($preset === 'yesterday') {
                $to = $now->copy()->subDay()->endOfDay();
            } elseif ($preset === 'this_week') {
                $to = $now->copy()->endOfWeek();
            } elseif ($preset === 'this_month') {
                $to = $now->copy()->endOfMonth();
            } else {
                $to = $now;
            }
        } else {
            $to = Carbon::now('Africa/Lagos');
        }

        $totalCustomers = Customer::whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->count();
        $convertedCustomers = Customer::whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->whereHas('orders', fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled)->where('is_migrated_order', false))->count();
        $conversionRate = $totalCustomers > 0 ? round(($convertedCustomers / $totalCustomers) * 100, 1) : 0;

        $revenue = Order::whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->where('status', '!=', OrderStatus::Cancelled)->where('is_migrated_order', false)->sum('total_price');
        $orders = Order::whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->where('status', '!=', OrderStatus::Cancelled)->where('is_migrated_order', false)->count();
        $totalUsers = User::count();
        $pendingTrialOrders = TrialOrder::where('status', TrialOrderStatus::ReceiptUploaded)->count();
        $pendingSalesRecords = SalesRecord::whereIn('status', ['pending', 'receipt_uploaded'])->count();
        $creditOutstanding = SalesRecord::where('is_credit', true)->where('status', 'approved')->where('credit_status', 'pending_payment')->sum('total_value');

        return [
            Stat::make('Total Customers', $totalCustomers)
                ->description($conversionRate.'% conversion rate')
                ->icon('heroicon-o-users')
                ->color('info')
                ->url(CustomerResource::getUrl('index')),
            Stat::make('Revenue', self::formatCurrency($revenue))
                ->description('Total revenue')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Orders', $orders)
                ->description('Orders placed')
                ->icon('heroicon-o-shopping-cart')
                ->color('primary')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Total Users', $totalUsers)
                ->description('All system users')
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->url(UserResource::getUrl('index')),
            Stat::make('Trial Orders', $pendingTrialOrders)
                ->description('Pending verification')
                ->icon('heroicon-o-beaker')
                ->color('warning')
                ->url(TrialOrderResource::getUrl('index')),
            Stat::make('Sales Records', $pendingSalesRecords)
                ->description('Pending verification')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(SalesRecordResource::getUrl('index')),
            Stat::make('Credit Outstanding', self::formatCurrency($creditOutstanding))
                ->description('Pending credit collection')
                ->icon('heroicon-o-clock')
                ->color('danger'),
        ];
    }

    protected static function formatCurrency(float $amount): string
    {
        if ($amount >= 1000000000) {
            return '₦'.number_format($amount / 1000000000, 1).'B';
        }
        if ($amount >= 1000000) {
            return '₦'.number_format($amount / 1000000, 1).'M';
        }
        if ($amount >= 1000) {
            return '₦'.number_format($amount / 1000, 1).'K';
        }

        return '₦'.number_format($amount, 0);
    }

    public static function canView(): bool
    {
        return auth()->user()->hasRole('general_manager');
    }
}
