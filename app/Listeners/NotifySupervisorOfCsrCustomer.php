<?php

namespace App\Listeners;

use App\Events\CustomerCreated;
use App\Models\User;
use App\Services\NotificationService;

class NotifySupervisorOfCsrCustomer
{
    public function handle(CustomerCreated $event): void
    {
        $csr = $event->csr;
        $customer = $event->customer;

        // Notify the paired Portfolio Agent
        if ($csr->portfolio_agent_id) {
            $portfolioAgent = User::find($csr->portfolio_agent_id);
            if ($portfolioAgent) {
                NotificationService::notifyUser(
                    $portfolioAgent->id,
                    'new_csr_customer',
                    'New Customer Added',
                    "CSR {$csr->name} added a new customer: {$customer->customer_name}",
                    $customer->id,
                    'customer'
                );
            }
        }
    }
}
