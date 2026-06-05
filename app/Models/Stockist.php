<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stockist extends Model
{
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $stockist) {
            if ($stockist->state_id && ! $stockist->state) {
                $state = State::find($stockist->state_id);
                $stockist->state = $state?->name;
                $stockist->region = $state?->region?->name;
            }
        });
    }

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
        'type',
        'is_trial_order_marketer',
        'state_id',
        'lga_id',
        'city_id',
    ];

    protected $casts = [
        'stock_balance' => 'decimal:2',
        'is_trial_order_marketer' => 'boolean',
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'stockist_id');
    }

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

    public function stateRelation(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function lgaRelation(): BelongsTo
    {
        return $this->belongsTo(Lga::class, 'lga_id');
    }

    public function cityRelation(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }
}
