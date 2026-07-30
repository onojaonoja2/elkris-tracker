<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Models\Concerns\HasSanitization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Order extends Model implements Auditable
{
    use AuditableTrait, HasFactory, HasSanitization;

    protected $fillable = [
        'customer_id',
        'user_id',
        'lga_id',
        'status',
        'is_migrated_order',
        'expected_delivery_date',
        'total_price',
        'payment_proof_path',
        'payment_proof_uploaded_by',
        'payment_proof_uploaded_at',
        'preferred_payment_option',
        'preferred_delivery_date',
        'delivery_details',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'assignment_status',
        'assignment_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'expected_delivery_date' => 'date',
            'assigned_at' => 'datetime',
            'payment_proof_uploaded_at' => 'datetime',
            'assignment_status' => AssignmentStatus::class,
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

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function paymentProofUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_proof_uploaded_by');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function hasPaymentProof(): bool
    {
        return ! empty($this->payment_proof_path);
    }

    public function isAssigned(): bool
    {
        return $this->assignment_status !== AssignmentStatus::None && $this->assigned_to !== null;
    }

    public function isBeingProcessedBySales(): bool
    {
        return $this->assigned_to !== null
            && $this->assignedTo?->role === 'sales'
            && $this->assignment_status === AssignmentStatus::Accepted;
    }

    public function isBeingProcessedByCsr(): bool
    {
        return $this->assigned_to !== null
            && $this->assignedTo?->role === 'community_sales_representative'
            && $this->assignment_status === AssignmentStatus::Accepted;
    }

    public function getProcessor(): ?User
    {
        return $this->assignedTo ?? $this->user;
    }

    protected array $sanitizableFields = [
        'delivery_details',
        'assignment_notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (Order $order) {
            $order->sanitizeFields($order->sanitizableFields);
        });

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
