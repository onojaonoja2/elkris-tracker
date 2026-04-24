<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockistStock extends Model
{
    protected $table = 'stockist_stocks';

    protected $fillable = [
        'stockist_id',
        'product_name',
        'grammage',
        'quantity',
        'unit_price',
    ];

    protected $casts = [
        'grammage' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function stockist(): BelongsTo
    {
        return $this->belongsTo(Stockist::class);
    }
}
