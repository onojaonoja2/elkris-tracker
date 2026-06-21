<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $fillable = ['stock_transfer_id', 'product_type_id', 'grammage', 'quantity', 'rejected_quantity', 'rejection_reason', 'rejection_resolved_at'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'grammage' => 'integer',
            'rejected_quantity' => 'integer',
            'rejection_resolved_at' => 'datetime',
        ];
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }
}
