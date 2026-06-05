<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    protected $fillable = [
        'from_warehouse_id', 'from_stockist_id', 'to_warehouse_id', 'to_stockist_id',
        'dispatched_by', 'received_by', 'received_at',
        'status', 'notes',
        'requested_by', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function fromStockist(): BelongsTo
    {
        return $this->belongsTo(Stockist::class, 'from_stockist_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function toStockist(): BelongsTo
    {
        return $this->belongsTo(Stockist::class, 'to_stockist_id');
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
}
