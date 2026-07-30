<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ProductionRun extends Model implements Auditable
{
    use AuditableTrait, HasFactory;

    protected $fillable = [
        'production_date',
        'output_name',
        'output_quantity',
        'output_unit',
        'status',
        'accountant_reviewed_by',
        'accountant_reviewed_at',
        'accountant_notes',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'output_quantity' => 'decimal:4',
            'production_date' => 'date',
            'accountant_reviewed_at' => 'datetime',
        ];
    }

    public function rawMaterials(): BelongsToMany
    {
        return $this->belongsToMany(RawMaterial::class, 'production_run_raw_materials')
            ->withPivot('quantity_used')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accountantReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountant_reviewed_by');
    }

    public function isLocked(): bool
    {
        return $this->status !== 'pending_review';
    }

    public function isReviewed(): bool
    {
        return $this->status === 'reviewed';
    }

    public function isFlagged(): bool
    {
        return $this->status === 'flagged';
    }

    public function getTotalQuantityUsed(): float
    {
        return (float) $this->rawMaterials->sum('pivot.quantity_used');
    }

    /**
     * @return array<int, array{raw_material_id: int, name: string, quantity_used: float, unit: string}>
     */
    public function getMaterialsSummary(): array
    {
        return $this->rawMaterials->map(fn (RawMaterial $material) => [
            'raw_material_id' => $material->id,
            'name' => $material->name,
            'quantity_used' => (float) $material->pivot->quantity_used,
            'unit' => $material->unit_of_measure,
        ])->toArray();
    }

    protected static function booted(): void
    {
        static::deleting(function (ProductionRun $run) {
            if ($run->status !== 'pending_review') {
                return;
            }

            foreach ($run->rawMaterials as $material) {
                $material->increment('quantity', (float) $material->pivot->quantity_used);
            }
        });
    }
}
