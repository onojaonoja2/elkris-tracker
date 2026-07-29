<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ProductType extends Model implements Auditable
{
    use AuditableTrait, HasFactory;

    protected $fillable = ['name', 'available_grammages', 'is_active'];

    protected function casts(): array
    {
        return [
            'available_grammages' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_type_id');
    }
}
