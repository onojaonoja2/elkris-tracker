<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'order_id',
        'product_type_id',
        'product_name',
        'grammage',
        'quantity',
        'price',
        'promotion_type',
        'free_quantity',
    ];

    /**
     * Get the order that owns this product entry.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (! $product->product_type_id && $product->product_name) {
                $product->product_type_id = ProductType::where('name', $product->product_name)->value('id');
            }
        });
    }
}
