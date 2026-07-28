<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            'admin', 'manager', 'lead', 'rep', 'sales',
            'supervisor', 'field_agent', 'community_sales_representative',
            'open_market', 'retail_market', 'general_manager',
            'accountant', 'general_accountant',
        ]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Customer $customer): bool
    {
        if (in_array($user->role, ['admin', 'manager', 'general_manager'])) {
            return true;
        }

        if (in_array($user->role, ['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])) {
            return $customer->agent_id === $user->id;
        }

        if ($user->role === 'lead') {
            return ($customer->lead_id ?? null) === $user->id
                || $customer->leads()->where('users.id', $user->id)->exists();
        }

        if ($user->role === 'rep') {
            return ($customer->rep_id ?? null) === $user->id
                || $customer->reps()->where('users.id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return ! in_array($user->role, ['sales', 'supervisor', 'accountant', 'warehouse_manager', 'general_accountant']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Customer $customer): bool
    {
        if (in_array($user->role, ['admin', 'manager', 'general_manager', 'general_accountant'])) {
            return true;
        }

        // Field agents can update their own
        if (in_array($user->role, ['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])) {
            return $customer->agent_id === $user->id;
        }

        // Leads can update customers submitted to them
        if ($user->role === 'lead' && ($customer->lead_id ?? null) === $user->id) {
            return true;
        }

        // Portfolio Agents can update customers from their paired CSRs
        if ($user->role === 'rep') {
            if ($customer->agent_id && User::where('portfolio_agent_id', $user->id)
                ->where('id', $customer->agent_id)
                ->exists()) {
                return true;
            }

            // Reps cannot update until they accept the assignment
            if ($customer->rep_acceptance_status === 'pending') {
                return false;
            }
        }

        // Check many-to-many pivots with query-level checks
        if ($customer->leads()->where('users.id', $user->id)->exists()) {
            return true;
        }

        if ($customer->reps()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return ($customer->lead_id ?? null) === $user->id || ($customer->rep_id ?? null) === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Customer $customer): bool
    {
        if (in_array($user->role, ['rep', 'field_agent', 'community_sales_representative', 'open_market', 'retail_market'])) {
            return false;
        }
        if (in_array($user->role, ['admin', 'manager', 'general_manager'])) {
            return true;
        }

        if ($customer->leads()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return ($customer->lead_id ?? null) === $user->id;
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
