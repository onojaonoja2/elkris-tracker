<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Repack extends Model implements Auditable
{
    use AuditableTrait, HasFactory;

    protected $fillable = [
        'warehouse_id',
        'user_id',
        'input_product_type_id',
        'input_grammage',
        'input_quantity',
        'output_product_type_id',
        'output_grammage',
        'output_quantity',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'input_grammage' => 'integer',
            'input_quantity' => 'integer',
            'output_grammage' => 'integer',
            'output_quantity' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inputProductType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'input_product_type_id');
    }

    public function outputProductType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'output_product_type_id');
    }
}
