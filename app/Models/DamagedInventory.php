<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class DamagedInventory extends Model implements Auditable
{
    use AuditableTrait, HasFactory;

    protected $table = 'damaged_inventory';

    protected $fillable = [
        'damaged_stock_return_id',
        'warehouse_id',
        'product_type_id',
        'grammage',
        'quantity',
        'status',
        'destination_warehouse_id',
        'dispatched_by',
        'dispatched_at',
        'received_by',
        'received_at',
        'destroyed_by',
        'destroyed_at',
        'destroy_reason',
    ];

    protected function casts(): array
    {
        return [
            'grammage' => 'integer',
            'quantity' => 'integer',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'destroyed_at' => 'datetime',
        ];
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('status', 'in_stock');
    }

    public function scopeDispatched(Builder $query): Builder
    {
        return $query->where('status', 'dispatched');
    }

    public function scopeDestroyed(Builder $query): Builder
    {
        return $query->where('status', 'destroyed');
    }

    public function damagedStockReturn(): BelongsTo
    {
        return $this->belongsTo(DamagedStockReturn::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function destroyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destroyed_by');
    }
}
