<?php

namespace App\Providers;

use App\Events\CustomerAssigned;
use App\Events\OrderCreated;
use App\Events\OrderDelivered;
use App\Events\TrialOrderApproved;
use App\Events\TrialOrderRejected;
use App\Listeners\NotifyAdminsOfDelivery;
use App\Listeners\NotifyAdminsOfUnassignedCustomer;
use App\Listeners\NotifyAgentOfTrialOrderOutcome;
use App\Listeners\NotifyLeadOfNewCustomer;
use App\Listeners\NotifyLeadOfNewOrder;
use App\Listeners\NotifyLeadOfTrialOrderOutcome;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderCreated::class => [NotifyLeadOfNewOrder::class],
        OrderDelivered::class => [NotifyAdminsOfDelivery::class],
        TrialOrderApproved::class => [NotifyLeadOfTrialOrderOutcome::class, NotifyAgentOfTrialOrderOutcome::class],
        TrialOrderRejected::class => [NotifyLeadOfTrialOrderOutcome::class, NotifyAgentOfTrialOrderOutcome::class],
        CustomerAssigned::class => [NotifyLeadOfNewCustomer::class, NotifyAdminsOfUnassignedCustomer::class],
    ];

    public function boot(): void {}
}
