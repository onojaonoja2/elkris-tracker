<?php

namespace App\Listeners;

use App\Events\CustomerAssigned;
use App\Services\NotificationService;

class NotifyAdminsOfUnassignedCustomer
{
    public function handle(CustomerAssigned $event): void
    {
        if ($event->assignedTo) {
            return;
        }

        NotificationService::notifyAdmins(
            'new_customer',
            'New Customer Submission',
            "New customer {$event->customer->name} requires assignment",
            $event->customer->id,
            'customer'
        );
    }
}
