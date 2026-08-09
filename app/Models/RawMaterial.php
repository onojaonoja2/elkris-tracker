<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
}
