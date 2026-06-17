<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\TrialOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrialOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'stockist_id',
        'products',
        'receipt_path',
        'receipt_original_name',
        'total_value',
        'status',
        'payment_status',
        'agent_balance',
        'stockist_balance',
        'accountant_verified_at',
        'accountant_verified_by',
        'supervisor_verified_at',
        'supervisor_verified_by',
        'accountant_notes',
        'supervisor_notes',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => TrialOrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'products' => 'array',
            'total_value' => 'decimal:2',
            'agent_balance' => 'decimal:2',
            'stockist_balance' => 'decimal:2',
            'accountant_verified_at' => 'datetime',
            'supervisor_verified_at' => 'datetime',
        ];
    }

    /**
     * Check if the trial order is locked (non-editable)
     */
    public function isLocked(): bool
    {
        return $this->payment_status === PaymentStatus::Completed
            && $this->status === TrialOrderStatus::Approved;
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function stockist(): BelongsTo
    {
        return $this->belongsTo(Stockist::class);
    }

    public function accountantVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountant_verified_by');
    }

    public function supervisorVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_verified_by');
    }
}
