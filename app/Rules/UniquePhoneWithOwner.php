<?php

namespace App\Rules;

use App\Models\Customer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniquePhoneWithOwner implements ValidationRule
{
    public function __construct(
        protected ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = Customer::where('phone_number', $value);

        if ($this->ignoreId) {
            $query->where('id', '!=', $this->ignoreId);
        }

        $existing = $query->first();

        if ($existing) {
            $owner = $this->getOwnerName($existing);
            $fail("This phone number is already registered under {$owner}.");
        }
    }

    protected function getOwnerName(Customer $customer): string
    {
        $agent = $customer->agent?->name;
        if ($agent) {
            return "{$agent} (Agent)";
        }

        $rep = $customer->rep?->name;
        if ($rep) {
            return "{$rep} (Rep)";
        }

        $lead = $customer->lead?->name;
        if ($lead) {
            return "{$lead} (Team Lead)";
        }

        return 'another customer';
    }
}
