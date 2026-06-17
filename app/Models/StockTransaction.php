<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransaction extends Model
{
    protected $fillable = [
        'type',
        'transaction_date',
        'product_type_id',
        'product_name',
        'grammage',
        'quantity',
        'disbursed_to',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'grammage' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
