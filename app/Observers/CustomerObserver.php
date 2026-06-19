<?php

namespace App\Observers;

use App\Events\CustomerAssigned;
use App\Models\Customer;

class CustomerObserver
{
    public function created(Customer $customer): void
    {
        $assignedTo = $customer->lead ?? $customer->rep ?? null;
        CustomerAssigned::dispatch($customer, $assignedTo);
    }

    public function updated(Customer $customer): void
    {
        if ($customer->wasChanged('lead_id')) {
            CustomerAssigned::dispatch($customer, $customer->lead);
        }

        if ($customer->wasChanged('rep_id')) {
            CustomerAssigned::dispatch($customer, $customer->rep);
        }
    }
}
