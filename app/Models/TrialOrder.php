<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
class TrialOrder extends Model
{
    /**
     * Payment status constants
     */
    public const PAYMENT_STATUS_PENDING = 'pending';

    public const PAYMENT_STATUS_CONFIRMED = 'confirmed';

    public const PAYMENT_STATUS_COMPLETED = 'completed';

    protected function casts(): array
    {
        return [
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
        return $this->payment_status === self::PAYMENT_STATUS_COMPLETED
            && $this->status === 'approved';
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
