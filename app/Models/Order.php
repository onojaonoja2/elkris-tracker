<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
class Order extends Model
{
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    protected static function booted()
    {
        static::updated(function (Order $order) {
            if ($order->isDirty('status') && $order->status === 'delivered') {
                $customer = $order->customer;
                $purchases = $customer->lifetime_purchases ?? [];

                foreach ($order->products as $product) {
                    $key = $product->product_name.' - '.$product->grammage.'g';
                    $purchases[$key] = ($purchases[$key] ?? 0) + $product->quantity;
                }

                $customer->update(['lifetime_purchases' => $purchases]);
            }
        });
    }
}
