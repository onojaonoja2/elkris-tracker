<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockistStock extends Model
{
    use HasFactory;

    protected $table = 'stockist_stocks';

    protected $fillable = [
        'stockist_id',
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

    public function stockist(): BelongsTo
    {
        return $this->belongsTo(Stockist::class);
    }
}
