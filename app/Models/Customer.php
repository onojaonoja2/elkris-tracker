<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
class Customer extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lifetime_purchases' => 'array',
            'rejected_at' => 'datetime',
            'replacement_requested_at' => 'datetime',
            'needs_replacement' => 'boolean',
        ];
    }

    /**
     * Get the lead assigned to this customer.
     */
    public function lead()
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    /**
     * Get the rep assigned to this customer.
     */
    public function rep()
    {
        return $this->belongsTo(User::class, 'rep_id');
    }

    /**
     * Get the leads assigned to this customer (many-to-many).
     */
    public function leads()
    {
        return $this->belongsToMany(User::class, 'customer_lead', 'customer_id', 'user_id')->withTimestamps();
    }

    /**
     * Get the reps assigned to this customer (many-to-many).
     */
    public function reps()
    {
        return $this->belongsToMany(User::class, 'customer_rep', 'customer_id', 'user_id')->withTimestamps();
    }

    /**
     * Get the products for this customer.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the field agent who submitted this customer.
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Get the orders for this customer.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the call logs for this customer.
     */
    public function callLogs()
    {
        return $this->hasMany(CallLog::class);
    }

    /**
     * Get the user who rejected this customer.
     */
    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get the user who requested replacement for this customer.
     */
    public function replacementRequestedBy()
    {
        return $this->belongsTo(User::class, 'replacement_requested_by');
    }

    /**
     * Get the 3-day follow-up date (from when customer was added to portfolio).
     */
    public function getFollowUp3DaysAttribute(): ?Carbon
    {
        if ($this->rep_acceptance_status !== 'accepted') {
            return null;
        }

        $pivot = \DB::table('customer_rep')
            ->where('customer_id', $this->id)
            ->where('user_id', $this->rep_id)
            ->first();

        if (! $pivot) {
            return null;
        }

        return Carbon::parse($pivot->created_at)->addDays(3);
    }

    /**
     * Get the 7-day follow-up date (from when customer was added to portfolio).
     */
    public function getFollowUp7DaysAttribute(): ?Carbon
    {
        if ($this->rep_acceptance_status !== 'accepted') {
            return null;
        }

        $pivot = \DB::table('customer_rep')
            ->where('customer_id', $this->id)
            ->where('user_id', $this->rep_id)
            ->first();

        if (! $pivot) {
            return null;
        }

        return Carbon::parse($pivot->created_at)->addDays(7);
    }

    /**
     * Get all follow-up dates (manual + auto-generated).
     */
    public function getAllFollowUpDatesAttribute(): array
    {
        $dates = [];

        if ($this->follow_up_date) {
            $dates[] = [
                'date' => $this->follow_up_date,
                'type' => 'manual',
                'label' => 'Manual Follow-up',
            ];
        }

        if ($this->follow_up_3_days) {
            $dates[] = [
                'date' => $this->follow_up_3_days,
                'type' => 'day_3',
                'label' => 'Day 3 Follow-up',
            ];
        }

        if ($this->follow_up_7_days) {
            $dates[] = [
                'date' => $this->follow_up_7_days,
                'type' => 'day_7',
                'label' => 'Day 7 Follow-up',
            ];
        }

        return $dates;
    }
}
