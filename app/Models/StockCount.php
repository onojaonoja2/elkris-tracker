<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class StockCount extends Model implements Auditable
{
    use AuditableTrait, HasFactory;

    protected $fillable = [
        'user_id',
        'warehouse_id',
        'is_additional_count',
        'parent_stock_count_id',
        'status',
        'supervisor_status',
        'supervisor_verified_by',
        'supervisor_verified_at',
        'notes',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_additional_count' => 'boolean',
            'supervisor_verified_at' => 'datetime',
            'approved_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supervisorVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_verified_by');
    }

    public function parentStockCount(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_stock_count_id');
    }

    public function childStockCounts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_stock_count_id');
    }
}
