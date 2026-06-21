<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentStock extends Model
{
    protected $fillable = [
        'user_id',
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

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }
}
