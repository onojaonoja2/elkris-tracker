<?php

namespace App\Observers;

use App\Events\CustomerAssigned;
use App\Models\Customer;

class CustomerObserver
{
    public function created(Customer $customer): void
    {
        CustomerAssigned::dispatch($customer, $customer->lead);
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
