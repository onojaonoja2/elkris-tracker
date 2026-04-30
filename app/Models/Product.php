<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
class Product extends Model
{
    /**
     * Get the order that owns this product entry.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
