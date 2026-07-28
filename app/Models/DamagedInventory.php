<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamagedInventory extends Model
{
    use HasFactory;

    protected $table = 'damaged_inventory';

    protected $fillable = [
        'damaged_stock_return_id',
        'warehouse_id',
        'product_type_id',
        'grammage',
        'quantity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'grammage' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function damagedStockReturn(): BelongsTo
    {
        return $this->belongsTo(DamagedStockReturn::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }
}
