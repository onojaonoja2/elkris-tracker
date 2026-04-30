<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stockist extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'city',
        'state',
        'region',
        'address',
        'stock_balance',
        'created_by',
        'supervisor_id',
    ];

    protected $casts = [
        'stock_balance' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(StockistStock::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StockistTransaction::class);
    }
}
