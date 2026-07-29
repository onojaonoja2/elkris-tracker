<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class DamagedStockReturn extends Model implements Auditable
{
    use AuditableTrait, HasFactory;

    protected $fillable = [
        'user_id',
        'warehouse_id',
        'product_type_id',
        'grammage',
        'quantity',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'supervisor_approved_by',
        'supervisor_approved_at',
        'accountant_approved_by',
        'accountant_approved_at',
        'return_to_warehouse_initiated_at',
        'return_to_warehouse_initiated_by',
        'return_received_at',
        'return_received_by',
    ];

    protected function casts(): array
    {
        return [
            'grammage' => 'integer',
            'quantity' => 'integer',
            'approved_at' => 'datetime',
            'supervisor_approved_at' => 'datetime',
            'accountant_approved_at' => 'datetime',
            'return_to_warehouse_initiated_at' => 'datetime',
            'return_received_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supervisorApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_approved_by');
    }

    public function accountantApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountant_approved_by');
    }

    public function returnInitiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_to_warehouse_initiated_by');
    }

    public function returnReceiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_received_by');
    }
}
