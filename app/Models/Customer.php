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
}
