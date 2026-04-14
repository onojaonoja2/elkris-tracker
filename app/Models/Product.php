<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
class Product extends Model
{
    /**
     * Get the customer that owns this product entry.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
