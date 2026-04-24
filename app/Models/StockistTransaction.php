<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockistTransaction extends Model
{
    protected $fillable = [
        'stockist_id',
        'user_id',
        'field_agent_id',
        'trial_order_id',
        'type',
        'amount',
        'description',
        'transaction_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function stockist(): BelongsTo
    {
        return $this->belongsTo(Stockist::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fieldAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_agent_id');
    }

    public function trialOrder(): BelongsTo
    {
        return $this->belongsTo(TrialOrder::class);
    }
}
