<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_count_id',
        'product_type_id',
        'product_name',
        'grammage',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'grammage' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }
}
