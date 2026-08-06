<?php

namespace App\Models;

use App\Models\Concerns\HasSanitization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class SalesRecord extends Model implements Auditable
{
    use AuditableTrait, HasFactory, HasSanitization;

    protected array $sanitizableFields = [
        'vendor_name',
        'business_name',
        'accountant_notes',
        'supervisor_notes',
        'customer_name',
        'customer_phone',
        'credit_notes',
        'rejection_reason',
    ];

    protected $fillable = [
        'agent_id',
        'agent_type',
        'customer_id',
        'products',
        'total_value',
        'vendor_name',
        'business_name',
        'receipt_path',
        'receipt_original_name',
        'payment_proof_path',
        'payment_proof_uploaded_by',
        'payment_proof_uploaded_at',
        'proof_review_requested_at',
        'proof_review_requested_by',
        'status',
        'stock_deducted_at',
        'rejection_reason',
        'accountant_verified_at',
        'accountant_verified_by',
        'supervisor_verified_at',
        'supervisor_verified_by',
        'accountant_notes',
        'supervisor_notes',
        'is_credit',
        'customer_name',
        'customer_phone',
        'expected_collection_date',
        'credit_status',
        'collected_at',
        'collected_by',
        'credit_notes',
    ];

    protected function casts(): array
    {
        return [
            'products' => 'array',
            'total_value' => 'decimal:2',
            'stock_deducted_at' => 'datetime',
            'payment_proof_uploaded_at' => 'datetime',
            'proof_review_requested_at' => 'datetime',
            'accountant_verified_at' => 'datetime',
            'supervisor_verified_at' => 'datetime',
            'is_credit' => 'boolean',
            'expected_collection_date' => 'date',
            'collected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SalesRecord $salesRecord) {
            $salesRecord->sanitizeFields($salesRecord->sanitizableFields);
        });
    }

    public function isLocked(): bool
    {
        return $this->status === 'approved' || $this->status === 'rejected';
    }

    public function isCsrSale(): bool
    {
        return $this->agent?->hasRole('community_sales_representative') ?? false;
    }

    public function isOutstanding(): bool
    {
        return blank($this->credit_status)
            || in_array($this->credit_status, ['pending_payment', 'partially_collected'], true);
    }

    public function outstandingAmount(): float
    {
        $collected = (float) $this->collections()->sum('collected_amount');

        return max(0, (float) $this->total_value - $collected);
    }

    public function isOverdue(): bool
    {
        return $this->isOutstanding()
            && $this->expected_collection_date
            && $this->expected_collection_date->isBefore(now()->toDateString());
    }

    public function hasPendingProofReview(): bool
    {
        return $this->proof_review_requested_at !== null && ! $this->hasPaymentProof();
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('is_credit', true)
            ->where('status', '!=', 'rejected')
            ->where(function (Builder $query) {
                $query->whereNull('credit_status')
                    ->orWhereIn('credit_status', ['pending_payment', 'partially_collected']);
            });
    }

    public function scopeCashApproved(Builder $query): Builder
    {
        return $query->where('is_credit', false)
            ->where('status', 'approved');
    }

    /**
     * Calculate realized revenue: cash sales + collected credit amounts.
     */
    public static function revenue(?array $agentIds, Carbon $from, Carbon $to): float
    {
        $cashRevenue = static::cashApproved()
            ->when($agentIds, fn ($q) => $q->whereIn('agent_id', $agentIds))
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_value');

        $collectedCredit = CreditCollection::whereHas('salesRecord', function ($q) use ($agentIds, $from, $to) {
            $q->where('status', 'approved')
                ->when($agentIds, fn ($q) => $q->whereIn('agent_id', $agentIds))
                ->whereBetween('created_at', [$from, $to]);
        })->sum('collected_amount');

        return (float) $cashRevenue + (float) $collectedCredit;
    }

    /**
     * Calculate realized revenue grouped by agent.
     *
     * @return Collection<int, object{agent_id: int, revenue: float}>
     */
    public static function revenueByAgent(array $agentIds, Carbon $from, Carbon $to): Collection
    {
        $cashByAgent = static::cashApproved()
            ->whereIn('agent_id', $agentIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('agent_id, COALESCE(SUM(total_value), 0) as revenue')
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');

        $collectedByAgent = CreditCollection::whereHas('salesRecord', function ($q) use ($agentIds, $from, $to) {
            $q->whereIn('agent_id', $agentIds)
                ->where('status', 'approved')
                ->whereBetween('created_at', [$from, $to]);
        })
            ->join('sales_records', 'credit_collections.sales_record_id', '=', 'sales_records.id')
            ->selectRaw('sales_records.agent_id, COALESCE(SUM(credit_collections.collected_amount), 0) as collected')
            ->groupBy('sales_records.agent_id')
            ->get()
            ->keyBy('agent_id');

        return collect($agentIds)->mapWithKeys(function (int $id) use ($cashByAgent, $collectedByAgent) {
            $cash = (float) ($cashByAgent->get($id)?->revenue ?? 0);
            $credit = (float) ($collectedByAgent->get($id)?->collected ?? 0);

            return [$id => (object) ['agent_id' => $id, 'revenue' => $cash + $credit]];
        });
    }

    /**
     * Calculate realized revenue grouped by state.
     *
     * @return Collection<string, object{state_name: ?string, revenue: float, total: int, pending: int, approved: int, rejected: int}>
     */
    public static function revenueByState(): Collection
    {
        $cashByState = static::cashApproved()
            ->leftJoin('users', 'sales_records.agent_id', '=', 'users.id')
            ->leftJoin('lgas', 'users.lga_id', '=', 'lgas.id')
            ->leftJoin('states as lga_state', 'lgas.state_id', '=', 'lga_state.id')
            ->selectRaw('lga_state.name as state_name, COALESCE(SUM(total_value), 0) as revenue')
            ->groupBy('lga_state.name')
            ->get()
            ->keyBy('state_name');

        $collectedByState = CreditCollection::whereHas('salesRecord', function ($q) {
            $q->where('status', 'approved');
        })
            ->join('sales_records', 'credit_collections.sales_record_id', '=', 'sales_records.id')
            ->leftJoin('users', 'sales_records.agent_id', '=', 'users.id')
            ->leftJoin('lgas', 'users.lga_id', '=', 'lgas.id')
            ->leftJoin('states as lga_state', 'lgas.state_id', '=', 'lga_state.id')
            ->selectRaw('lga_state.name as state_name, COALESCE(SUM(credit_collections.collected_amount), 0) as collected')
            ->groupBy('lga_state.name')
            ->get()
            ->keyBy('state_name');

        $allStates = $cashByState->keys()->merge($collectedByState->keys())->unique();

        return $allStates->mapWithKeys(function (?string $stateName) use ($cashByState, $collectedByState) {
            $cash = (float) ($cashByState->get($stateName)?->revenue ?? 0);
            $credit = (float) ($collectedByState->get($stateName)?->collected ?? 0);

            return [$stateName => (object) ['state_name' => $stateName, 'revenue' => $cash + $credit]];
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(CreditCollection::class);
    }

    public function proofReviewRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proof_review_requested_by');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function accountantVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountant_verified_by');
    }

    public function supervisorVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_verified_by');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function paymentProofUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_proof_uploaded_by');
    }

    public function hasPaymentProof(): bool
    {
        return $this->payment_proof_path !== null;
    }
}
