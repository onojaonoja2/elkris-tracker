<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Customer $customer): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Customer $customer): bool
    {
        if ($user->role === 'admin') return true;
        // Check many-to-many pivots if present, fall back to scalar columns
        if (method_exists($customer, 'leads') && $customer->leads()->exists()) {
            if ($customer->leads->contains($user->id)) return true;
        }
        if (method_exists($customer, 'reps') && $customer->reps()->exists()) {
            if ($customer->reps->contains($user->id)) return true;
        }

        return ($customer->lead_id ?? null) === $user->id || ($customer->rep_id ?? null) === $user->id;
    }
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Customer $customer): bool
    {
        if ($user->role === 'rep') return false; // Reps can't delete!
        if ($user->role === 'admin') return true;
        if (method_exists($customer, 'leads') && $customer->leads()->exists()) {
            return $customer->leads->contains($user->id);
        }

        return ($customer->lead_id ?? null) === $user->id; // Leads can delete their own
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Customer $customer): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        return false;
    }
}
