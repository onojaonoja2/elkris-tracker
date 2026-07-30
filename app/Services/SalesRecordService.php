<?php

namespace App\Services;

use App\Models\AgentStock;
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
        if ($record->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending sales records can be approved.',
            ]);
        }

        DB::transaction(function () use ($record, $data, $accountantId) {
            if (! $record->is_credit && $record->agent_id) {
                $record->agent?->increment('stock_balance', $record->total_value);
            }

            $record->update([
                'status' => 'approved',
                'accountant_verified_at' => now(),
                'accountant_verified_by' => $accountantId,
                'accountant_notes' => $data['accountant_notes'] ?? null,
            ]);
        });
    }

    /**
     * Reject a pending sales record and restore deducted stock.
     */
    public static function reject(SalesRecord $record, string $reason, int $accountantId): void
    {
        if ($record->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending sales records can be rejected.',
            ]);
        }

        DB::transaction(function () use ($record, $reason, $accountantId) {
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
     * Mark an approved credit sale as collected.
     *
     * @param  array<string, mixed>  $data
     */
    public static function markCollected(SalesRecord $record, array $data, int $collectorId): void
    {
        if (! $record->is_credit || $record->status !== 'approved' || $record->credit_status !== 'pending_payment') {
            throw ValidationException::withMessages([
                'status' => 'This credit sale is not ready for collection.',
            ]);
        }

        if (! $record->hasPaymentProof()) {
            throw ValidationException::withMessages([
                'payment_proof_path' => 'A payment proof must be uploaded before this credit sale can be marked as collected.',
            ]);
        }

        DB::transaction(function () use ($record, $data, $collectorId) {
            if ($record->agent_id) {
                $record->agent?->increment('stock_balance', $record->total_value);
            }

            $record->update([
                'credit_status' => 'collected',
                'collected_at' => now(),
                'collected_by' => $collectorId,
                'credit_notes' => $data['credit_notes'] ?? null,
            ]);
        });
    }

    /**
     * Attach a payment proof to an outstanding credit sale.
     *
     * @param  array<string, mixed>  $data
     */
    public static function attachPaymentProof(SalesRecord $record, array $data, int $uploaderId): void
    {
        if (! $record->is_credit || $record->status !== 'approved' || $record->credit_status !== 'pending_payment') {
            throw ValidationException::withMessages([
                'payment_proof_path' => 'Payment proof can only be attached to an outstanding approved credit sale.',
            ]);
        }

        $record->update([
            'payment_proof_path' => $data['payment_proof_path'],
            'payment_proof_uploaded_by' => $uploaderId,
            'payment_proof_uploaded_at' => now(),
        ]);
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
}
