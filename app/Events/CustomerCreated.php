<?php

namespace App\Events;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public User $csr,
    ) {}
}
