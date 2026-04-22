<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallLog extends Model
{
    protected $fillable = [
        'user_id',
        'customer_id',
        'called_at',
        'outcome',
        'notes',
        'other_comment',
    ];

    protected function casts(): array
    {
        return [
            'called_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
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
