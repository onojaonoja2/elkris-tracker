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
        if (in_array($user->role, ['admin', 'manager'])) return true;
        
        // Field agents can update their own
        if ($user->role === 'field_agent') return $customer->agent_id === $user->id;

        // Reps cannot update until they accept the assignment
        if ($user->role === 'rep' && $customer->rep_acceptance_status === 'pending') {
            return false;
        }

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
        if (in_array($user->role, ['rep', 'field_agent'])) return false; // Reps and Field Agents can't delete
        if (in_array($user->role, ['admin', 'manager'])) return true;
        
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
