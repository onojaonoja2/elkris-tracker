<?php

namespace App\Events;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerAssigned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Customer $customer, public ?User $assignedTo) {}
}
