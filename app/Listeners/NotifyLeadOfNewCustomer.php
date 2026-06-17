<?php

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\CustomerAssigned;
use App\Services\NotificationService;

class NotifyLeadOfNewCustomer
{
    public function handle(CustomerAssigned $event): void
    {
        $assignedTo = $event->assignedTo;

        if (! $assignedTo || $assignedTo->role !== UserRole::Lead->value) {
            return;
        }

        NotificationService::notifyUser(
            $assignedTo->id,
            'customer_assigned',
            'New Customer Assigned',
            "{$event->customer->name} has been assigned to you",
            $event->customer->id,
            'customer'
        );
    }
}
