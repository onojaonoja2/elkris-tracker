<?php

namespace App\Models;

use App\Enums\StockTransferStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class StockTransfer extends Model implements Auditable
{
    use AuditableTrait, HasFactory;

    protected $fillable = [
        'from_warehouse_id', 'from_agent_id', 'to_warehouse_id', 'to_agent_id',
        'dispatched_by', 'received_by', 'received_at',
        'status', 'notes',
        'requested_by', 'approved_by', 'approved_at', 'rejection_reason',
        'supervisor_approved_by', 'supervisor_approved_at',
        'source_type', 'source_name', 'sales_record_id', 'dispatch_papers_path', 'requires_approval',
        'collected_at', 'collected_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockTransferStatus::class,
            'received_at' => 'datetime',
            'approved_at' => 'datetime',
            'supervisor_approved_at' => 'datetime',
            'collected_at' => 'datetime',
        ];
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function salesRecord(): BelongsTo
    {
        return $this->belongsTo(SalesRecord::class, 'sales_record_id');
    }

    public function fromAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_agent_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function toAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_agent_id');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supervisorApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_approved_by');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function rejectedItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class)->where('rejected_quantity', '>', 0);
    }

    public function unresolvedRejectedItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class)
            ->where('rejected_quantity', '>', 0)
            ->whereNull('rejection_resolved_at');
    }

    public function isCollected(): bool
    {
        return $this->status === StockTransferStatus::Collected;
    }
}
