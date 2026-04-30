<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\TrialOrder;
use App\Observers\CustomerObserver;
use App\Observers\OrderObserver;
use App\Observers\TrialOrderObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Customer::observe(CustomerObserver::class);
        Order::observe(OrderObserver::class);
        TrialOrder::observe(TrialOrderObserver::class);
    }
}
