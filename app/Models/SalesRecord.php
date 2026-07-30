<?php

namespace App\Models;

use App\Models\Concerns\HasSanitization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class SalesRecord extends Model implements Auditable
{
    use AuditableTrait, HasSanitization;

    protected array $sanitizableFields = [
        'vendor_name',
        'business_name',
        'accountant_notes',
        'supervisor_notes',
        'customer_name',
        'customer_phone',
        'credit_notes',
        'rejection_reason',
    ];

    protected $fillable = [
        'agent_id',
        'agent_type',
        'products',
        'total_value',
        'vendor_name',
        'business_name',
        'receipt_path',
        'receipt_original_name',
        'payment_proof_path',
        'payment_proof_uploaded_by',
        'payment_proof_uploaded_at',
        'status',
        'stock_deducted_at',
        'rejection_reason',
        'accountant_verified_at',
        'accountant_verified_by',
        'supervisor_verified_at',
        'supervisor_verified_by',
        'accountant_notes',
        'supervisor_notes',
        'is_credit',
        'customer_name',
        'customer_phone',
        'expected_collection_date',
        'credit_status',
        'collected_at',
        'collected_by',
        'credit_notes',
    ];

    protected function casts(): array
    {
        return [
            'products' => 'array',
            'total_value' => 'decimal:2',
            'stock_deducted_at' => 'datetime',
            'payment_proof_uploaded_at' => 'datetime',
            'accountant_verified_at' => 'datetime',
            'supervisor_verified_at' => 'datetime',
            'is_credit' => 'boolean',
            'expected_collection_date' => 'date',
            'collected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SalesRecord $salesRecord) {
            $salesRecord->sanitizeFields($salesRecord->sanitizableFields);
        });
    }

    public function isLocked(): bool
    {
        return $this->status === 'approved' || $this->status === 'rejected';
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function accountantVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountant_verified_by');
    }

    public function supervisorVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_verified_by');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function paymentProofUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_proof_uploaded_by');
    }

    public function hasPaymentProof(): bool
    {
        return $this->payment_proof_path !== null;
    }
}
