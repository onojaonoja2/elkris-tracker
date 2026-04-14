<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
class Customer extends Model
{
    /**
     * Get the lead assigned to this customer.
     */
    public function lead()
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    /**
     * Get the rep assigned to this customer.
     */
    public function rep()
    {
        return $this->belongsTo(User::class, 'rep_id');
    }

    /**
     * Get the leads assigned to this customer (many-to-many).
     */
    public function leads()
    {
        return $this->belongsToMany(User::class, 'customer_lead', 'customer_id', 'user_id')->withTimestamps();
    }

    /**
     * Get the reps assigned to this customer (many-to-many).
     */
    public function reps()
    {
        return $this->belongsToMany(User::class, 'customer_rep', 'customer_id', 'user_id')->withTimestamps();
    }

    /**
     * Get the products for this customer.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the field agent who submitted this customer.
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
