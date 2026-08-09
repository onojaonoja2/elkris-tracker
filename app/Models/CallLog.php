<?php

namespace App\Models;

use App\Enums\CallOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class CallLog extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = [
        'user_id',
        'customer_id',
        'called_at',
        'next_call_date',
        'outcome',
        'notes',
        'other_comment',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => CallOutcome::class,
            'called_at' => 'datetime',
            'next_call_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeForRep($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForTeam($query, int $leadId)
    {
        return $query->whereIn('user_id', function ($q) use ($leadId) {
            $q->select('id')
                ->from('users')
                ->where('lead_id', $leadId)
                ->orWhere('id', $leadId);
        });
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('called_at', $date);
    }
}
