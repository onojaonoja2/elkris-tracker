<?php

namespace App\Models;

use App\Enums\CustomerPriority;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'rep_id',
        'agent_id',
        'submission_target_type',
        'customer_name',
        'phone_number',
        'age',
        'gender',
        'city',
        'address',
        'customer_status',
        'priority',
        'diabetic_awareness',
        'call_date',
        'preffered_call_time',
        'feedback',
        'remarks',
        'follow_up_date',
        'order_quantity',
        'rejection_note',
        'rejected_at',
        'rejected_by',
        'needs_replacement',
        'replacement_requested_by',
        'replacement_requested_at',
        'rep_acceptance_status',
        'trial_order_purchase',
        'region',
        'state',
        'state_id',
        'lga_id',
        'city_id',
        'is_payment_verified',
        'preferred_payment_option',
        'total_price',
        'preferred_delivery_date',
        'lifetime_purchases',
        'sort',
    ];

    public function setPhoneNumberAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['phone_number'] = null;

            return;
        }

        $digits = preg_replace('/[^0-9]/', '', $value);

        if (strlen($digits) === 10) {
            $digits = '0'.$digits;
        }

        $this->attributes['phone_number'] = $digits;
    }

    protected static function booted(): void
    {
        static::creating(function (self $customer) {
            if ($customer->state_id && ! $customer->state) {
                $state = State::find($customer->state_id);
                $customer->state = $state?->name;
                $customer->region = $state?->region?->name;
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => CustomerPriority::class,
            'lifetime_purchases' => 'array',
            'rejected_at' => 'datetime',
            'replacement_requested_at' => 'datetime',
            'needs_replacement' => 'boolean',
        ];
    }

    /**
     * Get the lead assigned to this customer.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    /**
     * Get the rep assigned to this customer.
     */
    public function rep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rep_id');
    }

    /**
     * Get the leads assigned to this customer (many-to-many).
     */
    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'customer_lead', 'customer_id', 'user_id')->withTimestamps();
    }

    /**
     * Get the reps assigned to this customer (many-to-many).
     */
    public function reps(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'customer_rep', 'customer_id', 'user_id')->withTimestamps();
    }

    /**
     * Get the products for this customer.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the field agent who submitted this customer.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Get the orders for this customer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the call logs for this customer.
     */
    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class);
    }

    /**
     * Get the user who rejected this customer.
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get the user who requested replacement for this customer.
     */
    public function replacementRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replacement_requested_by');
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

    /**
     * Get customers submitted to a manager (retail/open_market agents).
     */
    public function scopeSubmittedToManager($query, int $managerId)
    {
        return $query->where('submission_target_type', 'manager')
            ->where('lead_id', $managerId)
            ->whereNull('rep_id');
    }

    /**
     * Get customers submitted by CSR to this lead (via portfolio_agent_id).
     */
    public function scopeSubmittedToLead($query, int $leadId)
    {
        return $query->where('submission_target_type', 'lead')
            ->where('lead_id', $leadId)
            ->where('rep_acceptance_status', 'pending')
            ->whereNull('rep_id');
    }

    /**
     * Get customers submitted by CSR to this rep (via portfolio_agent_id).
     */
    public function scopeSubmittedToRep($query, int $repId)
    {
        return $query->where('submission_target_type', 'rep')
            ->where('rep_id', $repId)
            ->where('rep_acceptance_status', 'pending');
    }
}
