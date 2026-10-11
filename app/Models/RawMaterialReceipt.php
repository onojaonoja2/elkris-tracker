<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class RawMaterialReceipt extends Model implements Auditable
{
    use AuditableTrait, HasFactory;

    protected $fillable = [
        'warehouse_id',
        'source_type',
        'supplier_name',
        'items',
        'dispatch_papers_path',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSupplierSource(): bool
    {
        return $this->source_type !== 'other';
    }

    /**
     * The source label: the supplier name for procurement receipts or the
     * free-text source name for receipts from other sources.
     */
    public function sourceName(): ?string
    {
        return $this->supplier_name;
    }

    /**
     * Decoded dispatch papers attached to the receipt, if any.
     *
     * @return array<int, string>
     */
    public function dispatchPapers(): array
    {
        if (blank($this->dispatch_papers_path)) {
            return [];
        }

        $decoded = json_decode((string) $this->dispatch_papers_path, true);

        return is_array($decoded) ? $decoded : [(string) $this->dispatch_papers_path];
    }

    /**
     * @return array<int, array{raw_material_id: int, quantity: float}>
     */
    public function lines(): array
    {
        return collect($this->items ?? [])
            ->filter(fn ($item): bool => is_array($item) && isset($item['raw_material_id']) && (float) ($item['quantity'] ?? 0) > 0)
            ->map(fn (array $item): array => [
                'raw_material_id' => (int) $item['raw_material_id'],
                'quantity' => (float) $item['quantity'],
            ])
            ->values()
            ->all();
    }
}
