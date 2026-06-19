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

        if ($csr->portfolio_agent_id) {
            $pairedAgent = User::find($csr->portfolio_agent_id);
            if ($pairedAgent) {
                $roleLabel = $pairedAgent->role === 'lead' ? 'Team Lead' : 'Rep';
                NotificationService::notifyUser(
                    $pairedAgent->id,
                    'new_csr_customer',
                    'New CSR Customer',
                    "CSR {$csr->name} added a new customer: {$customer->customer_name}. Please review and accept.",
                    $customer->id,
                    'customer'
                );
            }
        }
    }
}
