<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'lga_id',
        'status',
        'is_migrated_order',
        'expected_delivery_date',
        'total_price',
        'preferred_payment_option',
        'preferred_delivery_date',
        'delivery_details',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'expected_delivery_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected static function booted(): void
    {
        static::updated(function (Order $order) {
            if ($order->isDirty('status') && $order->status === OrderStatus::Delivered) {
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
