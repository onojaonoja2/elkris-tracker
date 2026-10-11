<?php

namespace App\Livewire\Concerns;

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Attributes\Computed;

trait ViewsOrderPaymentProof
{
    private const array PAYMENT_PROOF_VIEWER_ROLES = [
        'accountant',
        'general_accountant',
        'supervisor',
        'admin',
        'manager',
        'general_manager',
    ];

    public ?int $proofOrderId = null;

    public function canViewPaymentProof(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(self::PAYMENT_PROOF_VIEWER_ROLES);
    }

    #[Computed]
    public function proofOrder(): ?Order
    {
        if ($this->proofOrderId === null || ! $this->canViewPaymentProof()) {
            return null;
        }

        return Order::query()
            ->where('status', OrderStatus::Delivered)
            ->with('paymentProofUploader')
            ->find($this->proofOrderId);
    }

    public function viewProof(int $orderId): void
    {
        if (! $this->canViewPaymentProof()) {
            abort(403);
        }

        $order = Order::query()
            ->where('status', OrderStatus::Delivered)
            ->find($orderId);

        if ($order === null || ! $order->hasPaymentProof()) {
            return;
        }

        $this->proofOrderId = $order->id;
        unset($this->proofOrder);
    }

    public function closeProof(): void
    {
        $this->proofOrderId = null;
        unset($this->proofOrder);
    }
}
