<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ProductionRun extends Model implements Auditable
{
    use AuditableTrait, HasFactory;

    protected $fillable = [
        'raw_material_id',
        'quantity_used',
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
            'quantity_used' => 'decimal:4',
            'output_quantity' => 'decimal:4',
            'production_date' => 'date',
            'accountant_reviewed_at' => 'datetime',
        ];
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
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
}
