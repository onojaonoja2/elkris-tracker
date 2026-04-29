<?php

namespace App\Observers;

use App\Models\Customer;
use App\Services\NotificationService;

class CustomerObserver
{
    public function created(Customer $customer): void
    {
        $lead = $customer->lead;
        if ($lead) {
            NotificationService::notifyRep(
                $lead->id,
                'customer_assigned',
                'New Customer Assigned',
                "{$customer->name} has been assigned to you",
                $customer->id,
                'customer'
            );
        } else {
            NotificationService::notifyAdmins(
                'new_customer',
                'New Customer Submission',
                "New customer {$customer->name} requires assignment",
                $customer->id,
                'customer'
            );
        }
    }

    public function updated(Customer $customer): void
    {
        if ($customer->wasChanged('lead_id') && $customer->lead_id) {
            NotificationService::notifyRep(
                $customer->lead_id,
                'customer_assigned',
                'Customer Assigned',
                "{$customer->name} has been assigned to you",
                $customer->id,
                'customer'
            );
        }

        if ($customer->wasChanged('rep_id') && $customer->rep_id) {
            NotificationService::notifyRep(
                $customer->rep_id,
                'customer_assigned',
                'Customer Assigned',
                "{$customer->name} has been assigned to you",
                $customer->id,
                'customer'
            );
        }
    }
}
