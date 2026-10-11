<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class RawMaterial extends Model implements Auditable
{
    use AuditableTrait, HasFactory;

    protected $fillable = [
        'name',
        'unit_of_measure',
        'quantity',
        'reorder_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'reorder_level' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function productionRuns(): BelongsToMany
    {
        return $this->belongsToMany(ProductionRun::class, 'production_run_raw_materials')
            ->withPivot('quantity_used')
            ->withTimestamps();
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(RawMaterialStock::class);
    }

    /**
     * Quantity of this material held in a specific warehouse, or the total
     * across all warehouses. Falls back to the legacy global pool when no
     * per-warehouse rows exist yet.
     */
    public function availableQuantity(?int $warehouseId = null): float
    {
        if ($warehouseId !== null) {
            return (float) ($this->stocks()->where('warehouse_id', $warehouseId)->value('quantity') ?? 0);
        }

        if ($this->stocks()->exists()) {
            return (float) $this->stocks()->sum('quantity');
        }

        return (float) $this->quantity;
    }
}
