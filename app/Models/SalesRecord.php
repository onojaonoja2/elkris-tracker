<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesRecord extends Model
{
    protected $fillable = [
        'agent_id',
        'agent_type',
        'products',
        'total_value',
        'vendor_name',
        'business_name',
        'receipt_path',
        'receipt_original_name',
        'status',
        'stockist_id',
        'stockist_balance',
        'accountant_verified_at',
        'accountant_verified_by',
        'supervisor_verified_at',
        'supervisor_verified_by',
        'accountant_notes',
        'supervisor_notes',
    ];

    protected function casts(): array
    {
        return [
            'products' => 'array',
            'total_value' => 'decimal:2',
            'stockist_balance' => 'decimal:2',
            'accountant_verified_at' => 'datetime',
            'supervisor_verified_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->status === 'approved' || $this->status === 'rejected';
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
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
