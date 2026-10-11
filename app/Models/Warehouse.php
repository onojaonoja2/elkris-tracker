<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'type', 'phone', 'address',
        'state_id', 'lga_id', 'city_id',
        'manager_id', 'sales_person_id', 'is_active',
        'has_production_module', 'has_raw_material_module',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'has_production_module' => 'boolean',
            'has_raw_material_module' => 'boolean',
        ];
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class, 'lga_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_person_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_warehouse_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_warehouse_id');
    }

    public function rawMaterialStocks(): HasMany
    {
        return $this->hasMany(RawMaterialStock::class);
    }

    public function isProductionStore(): bool
    {
        return $this->has_production_module;
    }

    public function isRawMaterialStore(): bool
    {
        return $this->has_raw_material_module;
    }

    public function scopeWithProductionModule(Builder $query): Builder
    {
        return $query->where('has_production_module', true);
    }

    public function scopeWithRawMaterialModule(Builder $query): Builder
    {
        return $query->where('has_raw_material_module', true);
    }

    /**
     * @return array<int>
     */
    public static function productionModuleIds(): array
    {
        return static::withProductionModule()->where('is_active', true)->orderBy('id')->pluck('id')->all();
    }

    /**
     * @return array<int>
     */
    public static function rawMaterialModuleIds(): array
    {
        return static::withRawMaterialModule()->where('is_active', true)->orderBy('id')->pluck('id')->all();
    }

    /**
     * @deprecated Use withProductionModule() — multiple warehouses can now
     * hold produced items. Kept as a fallback while callers are migrated.
     */
    public static function productionStore(): ?self
    {
        return static::withProductionModule()->where('is_active', true)->first();
    }
}
