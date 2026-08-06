<?php

namespace App\Services;

use App\Models\AgentStock;
use App\Models\CreditCollection;
use App\Models\SalesRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesRecordService
{
    /**
     * Create a sales record and immediately deduct the agent's stock.
     *
     * @param  array<string, mixed>  $data
     */
    public static function submitSale(array $data, ?int $agentId = null): SalesRecord
    {
        $agentId ??= auth()->id();

        return DB::transaction(function () use ($data, $agentId) {
            $products = $data['products'] ?? [];

            foreach ($products as $product) {
                $productName = $product['product_name'] ?? null;
                $grammage = $product['grammage'] ?? null;
                $quantity = (int) ($product['quantity'] ?? 0);

                if (! $productName || ! $grammage || $quantity <= 0) {
                    continue;
                }

                $agentStock = AgentStock::where('user_id', $agentId)
                    ->where('product_name', $productName)
                    ->where('grammage', $grammage)
                    ->lockForUpdate()
                    ->first();

                if (! $agentStock || $agentStock->quantity < $quantity) {
                    throw ValidationException::withMessages([
                        'products' => "Insufficient stock for {$productName} ({$grammage}g). Available: ".($agentStock?->quantity ?? 0),
                    ]);
                }
            }

            // All stock checks passed — perform deductions and create record.
            foreach ($products as $product) {
                $productName = $product['product_name'] ?? null;
                $grammage = $product['grammage'] ?? null;
                $quantity = (int) ($product['quantity'] ?? 0);

                if (! $productName || ! $grammage || $quantity <= 0) {
                    continue;
                }

                AgentStock::where('user_id', $agentId)
                    ->where('product_name', $productName)
                    ->where('grammage', $grammage)
                    ->lockForUpdate()
                    ->first()
                    ?->decrement('quantity', $quantity);
            }

            $isCredit = ! empty($data['is_credit']);

            $recordData = array_merge($data, [
                'agent_id' => $agentId,
                'agent_type' => $data['agent_type'] ?? auth()->user()?->getPrimaryRole(),
                'status' => 'pending',
                'stock_deducted_at' => now(),
                'total_value' => self::computeTotalValue($products),
                'is_credit' => $isCredit,
                'credit_status' => $isCredit ? 'pending_payment' : null,
            ]);

            return SalesRecord::create($recordData);
        });
    }

    /**
     * Approve a pending sales record. Stock has already moved at submission.
     *
     * @param  array<string, mixed>  $data
     */
    public static function approve(SalesRecord $record, array $data, int $accountantId): void
    {
        DB::transaction(function () use ($record, $data, $accountantId) {
            $record = SalesRecord::whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($record->status, ['pending', 'receipt_uploaded'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending sales records can be approved.',
                ]);
            }

            if ($record->isCsrSale() && $record->supervisor_verified_at === null) {
                throw ValidationException::withMessages([
                    'status' => 'This sales record must be approved by the supervisor first.',
                ]);
            }

            if (! $record->is_credit && $record->agent_id) {
                $record->agent?->increment('stock_balance', $record->total_value);
            }

            $record->update([
                'status' => 'approved',
                'accountant_verified_at' => now(),
                'accountant_verified_by' => $accountantId,
                'accountant_notes' => $data['accountant_notes'] ?? null,
                'credit_status' => $record->is_credit && blank($record->credit_status)
                    ? 'pending_payment'
                    : $record->credit_status,
            ]);
        });
    }

    /**
     * Approve a pending CSR sales record, forwarding it to the accountant for final approval.
     */
    public static function supervisorApprove(SalesRecord $record, ?string $notes, int $supervisorId): void
    {
        DB::transaction(function () use ($record, $notes, $supervisorId) {
            $record = SalesRecord::whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($record->status, ['pending', 'receipt_uploaded'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending sales records can be approved.',
                ]);
            }

            if (! $record->isCsrSale()) {
                throw ValidationException::withMessages([
                    'status' => 'Only CSR sales records can be approved by the supervisor.',
                ]);
            }

            if ($record->supervisor_verified_at !== null) {
                throw ValidationException::withMessages([
                    'status' => 'This sales record has already been approved by the supervisor.',
                ]);
            }

            $record->update([
                'supervisor_verified_at' => now(),
                'supervisor_verified_by' => $supervisorId,
                'supervisor_notes' => $notes,
            ]);
        });
    }

    /**
     * Reject a pending CSR sales record and restore deducted stock.
     */
    public static function supervisorReject(SalesRecord $record, string $reason, int $supervisorId): void
    {
        DB::transaction(function () use ($record, $reason, $supervisorId) {
            $record = SalesRecord::whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($record->status, ['pending', 'receipt_uploaded'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending sales records can be rejected.',
                ]);
            }

            if (! $record->isCsrSale()) {
                throw ValidationException::withMessages([
                    'status' => 'Only CSR sales records can be rejected by the supervisor.',
                ]);
            }

            if ($record->stock_deducted_at !== null) {
                self::restoreStock($record);
            }

            $record->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'supervisor_verified_at' => now(),
                'supervisor_verified_by' => $supervisorId,
            ]);
        });
    }

    /**
     * Reject a pending sales record and restore deducted stock.
     */
    public static function reject(SalesRecord $record, string $reason, int $accountantId): void
    {
        DB::transaction(function () use ($record, $reason, $accountantId) {
            $record = SalesRecord::whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($record->status, ['pending', 'receipt_uploaded'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending sales records can be rejected.',
                ]);
            }

            if ($record->stock_deducted_at !== null) {
                self::restoreStock($record);
            }

            $record->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'accountant_verified_at' => now(),
                'accountant_verified_by' => $accountantId,
            ]);
        });
    }

    /**
     * Record a partial or full collection against an approved credit sale.
     *
     * @param  array<string, mixed>  $data
     */
    public static function recordCollection(SalesRecord $record, array $data, int $collectorId): void
    {
        DB::transaction(function () use ($record, $data, $collectorId) {
            $record = SalesRecord::whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            self::assertCollectable($record);

            $amount = (float) ($data['collected_amount'] ?? 0);
            $outstanding = $record->outstandingAmount();

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'collected_amount' => 'The collection amount must be greater than zero.',
                ]);
            }

            if ($amount > $outstanding) {
                throw ValidationException::withMessages([
                    'collected_amount' => "The collection amount ({$amount}) exceeds the outstanding balance ({$outstanding}).",
                ]);
            }

            CreditCollection::create([
                'sales_record_id' => $record->id,
                'collected_amount' => $amount,
                'collected_at' => now(),
                'collected_by' => $collectorId,
                'payment_proof_path' => $data['payment_proof_path'] ?? null,
                'notes' => $data['credit_notes'] ?? null,
            ]);

            $remaining = $record->outstandingAmount();

            if ($remaining <= 0) {
                $record->update([
                    'credit_status' => 'collected',
                    'collected_at' => now(),
                    'collected_by' => $collectorId,
                    'credit_notes' => $data['credit_notes'] ?? null,
                ]);

                if ($record->agent_id) {
                    $record->agent?->increment('stock_balance', $record->total_value);
                }
            } else {
                $record->update([
                    'credit_status' => 'partially_collected',
                ]);
            }
        });
    }

    /**
     * Mark an approved credit sale as collected (full amount).
     *
     * @param  array<string, mixed>  $data
     */
    public static function markCollected(SalesRecord $record, array $data, int $collectorId): void
    {
        self::recordCollection($record, array_merge($data, ['collected_amount' => $record->total_value]), $collectorId);
    }

    /**
     * Attach a payment proof to an outstanding credit sale.
     *
     * @param  array<string, mixed>  $data
     */
    public static function attachPaymentProof(SalesRecord $record, array $data, int $uploaderId): void
    {
        DB::transaction(function () use ($record, $data, $uploaderId) {
            $record = SalesRecord::whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (! $record->is_credit || $record->status !== 'approved' || ! $record->isOutstanding()) {
                throw ValidationException::withMessages([
                    'payment_proof_path' => 'Payment proof can only be attached to an outstanding approved credit sale.',
                ]);
            }

            if ($record->hasPaymentProof()) {
                throw ValidationException::withMessages([
                    'payment_proof_path' => 'A payment proof is already uploaded.',
                ]);
            }

            $record->update([
                'payment_proof_path' => $data['payment_proof_path'],
                'payment_proof_uploaded_by' => $uploaderId,
                'payment_proof_uploaded_at' => now(),
                'proof_review_requested_at' => null,
                'proof_review_requested_by' => null,
            ]);
        });
    }

    /**
     * Mark an outstanding credit sale as awaiting accountant payment-proof review.
     */
    public static function requestProofReview(SalesRecord $record, int $requesterId): void
    {
        DB::transaction(function () use ($record, $requesterId) {
            $record = SalesRecord::whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (! $record->is_credit || $record->status !== 'approved' || ! $record->isOutstanding()) {
                throw ValidationException::withMessages([
                    'status' => 'Only approved outstanding credit sales can request a proof review.',
                ]);
            }

            if ($record->agent_id !== $requesterId) {
                throw ValidationException::withMessages([
                    'status' => 'You can only request a proof review for your own credit sales.',
                ]);
            }

            if ($record->hasPaymentProof()) {
                throw ValidationException::withMessages([
                    'payment_proof_path' => 'A payment proof is already uploaded for this sale.',
                ]);
            }

            if ($record->hasPendingProofReview()) {
                throw ValidationException::withMessages([
                    'status' => 'A proof review is already pending for this sale.',
                ]);
            }

            $record->update([
                'proof_review_requested_at' => now(),
                'proof_review_requested_by' => $requesterId,
            ]);
        });

        NotificationService::notifyRoles(
            ['admin', 'supervisor', 'accountant', 'general_accountant'],
            'credit_proof_review_requested',
            'Payment proof review requested',
            "Agent #{$record->agent_id} has requested a payment proof review for credit sale #{$record->id}.",
            $record->id,
            'sales_record'
        );
    }

    /**
     * Restore deducted stock to the agent.
     */
    protected static function restoreStock(SalesRecord $record): void
    {
        foreach ($record->products ?? [] as $product) {
            $productName = $product['product_name'] ?? null;
            $grammage = $product['grammage'] ?? null;
            $quantity = (int) ($product['quantity'] ?? 0);

            if (! $productName || ! $grammage || $quantity <= 0 || ! $record->agent_id) {
                continue;
            }

            AgentStock::where('user_id', $record->agent_id)
                ->where('product_name', $productName)
                ->where('grammage', $grammage)
                ->lockForUpdate()
                ->first()
                ?->increment('quantity', $quantity);
        }
    }

    protected static function assertCollectable(SalesRecord $record): void
    {
        if (! $record->is_credit || $record->status !== 'approved' || ! $record->isOutstanding()) {
            throw ValidationException::withMessages([
                'status' => 'This credit sale is not ready for collection.',
            ]);
        }

        if (! $record->hasPaymentProof()) {
            throw ValidationException::withMessages([
                'payment_proof_path' => 'A payment proof must be uploaded before this credit sale can be marked as collected.',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    protected static function computeTotalValue(array $products): float
    {
        $total = 0;

        foreach ($products as $product) {
            $quantity = (float) ($product['quantity'] ?? 0);
            $price = (float) ($product['price'] ?? 0);
            $total += $quantity * $price;
        }

        return round($total, 2);
    }
}
