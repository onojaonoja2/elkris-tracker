<?php

namespace App\Models;

use App\Models\Concerns\HasSanitization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCollection extends Model
{
    use HasFactory, HasSanitization;

    protected $fillable = [
        'sales_record_id',
        'collected_amount',
        'collected_at',
        'collected_by',
        'payment_proof_path',
        'notes',
    ];

    protected array $sanitizableFields = [
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'collected_amount' => 'decimal:2',
            'collected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CreditCollection $collection) {
            $collection->sanitizeFields($collection->sanitizableFields);
        });
    }

    public function salesRecord(): BelongsTo
    {
        return $this->belongsTo(SalesRecord::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
