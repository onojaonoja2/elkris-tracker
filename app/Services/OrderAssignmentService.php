<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Events\OrderAssigned;
use App\Events\OrderAssignmentAccepted;
use App\Events\OrderAssignmentRejected;
use App\Models\AgentStock;
use App\Models\Order;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderAssignmentService
{
    public static function assignToCsr(Order $order, User $csr, ?string $notes = null): void
    {
        DB::transaction(function () use ($order, $csr, $notes) {
            $order->update([
                'assigned_to' => $csr->id,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
                'assignment_status' => AssignmentStatus::Assigned,
                'assignment_notes' => $notes,
                'status' => OrderStatus::Assigned,
            ]);

            OrderAssigned::dispatch($order, $csr);
        });
    }

    public static function acceptAssignment(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->update([
                'assignment_status' => AssignmentStatus::Accepted,
            ]);

            OrderAssignmentAccepted::dispatch($order);
        });
    }

    public static function rejectAssignment(Order $order, ?string $reason = null): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order->update([
                'assigned_to' => null,
                'assigned_by' => null,
                'assigned_at' => null,
                'assignment_status' => AssignmentStatus::None,
                'assignment_notes' => $reason,
                'status' => OrderStatus::Pending,
            ]);

            OrderAssignmentRejected::dispatch($order);
        });
    }

    public static function attachPaymentProof(Order $order, string $path, int $uploadedBy): void
    {
        DB::transaction(function () use ($order, $path, $uploadedBy) {
            $order->update([
                'payment_proof_path' => $path,
                'payment_proof_uploaded_by' => $uploadedBy,
                'payment_proof_uploaded_at' => now(),
            ]);
        });
    }

    public static function confirmDeliveryByCsr(Order $order): void
    {
        if (! $order->hasPaymentProof()) {
            throw ValidationException::withMessages([
                'payment_proof' => 'A payment proof must be uploaded before this order can be marked as delivered.',
            ]);
        }

        DB::transaction(function () use ($order) {
            $processor = $order->assignedTo;

            if ($processor) {
                foreach ($order->products as $product) {
                    $stock = AgentStock::where([
                        'user_id' => $processor->id,
                        'product_name' => $product->product_name,
                        'grammage' => $product->grammage,
                    ])->first();

                    if ($stock && $stock->quantity >= $product->quantity) {
                        $stock->decrement('quantity', $product->quantity);

                        StockTransaction::create([
                            'type' => 'disbursed',
                            'transaction_date' => now()->toDateString(),
                            'product_type_id' => $product->product_type_id,
                            'product_name' => $product->product_name,
                            'grammage' => $product->grammage,
                            'quantity' => $product->quantity,
                            'disbursed_to' => 'Order #'.$order->id.' delivery',
                            'user_id' => $processor->id,
                        ]);
                    }
                }

                $processor->increment('stock_balance', $order->total_price);
            }

            $order->update([
                'status' => OrderStatus::Delivered,
                'assignment_status' => AssignmentStatus::Delivered,
            ]);
        });
    }

    public static function confirmDeliveryBySales(Order $order): void
    {
        if (! $order->hasPaymentProof()) {
            throw ValidationException::withMessages([
                'payment_proof' => 'A payment proof must be uploaded before this order can be marked as delivered.',
            ]);
        }

        DB::transaction(function () use ($order) {
            $processor = $order->assignedTo;

            if ($processor) {
                foreach ($order->products as $product) {
                    $stock = AgentStock::where([
                        'user_id' => $processor->id,
                        'product_name' => $product->product_name,
                        'grammage' => $product->grammage,
                    ])->first();

                    if ($stock && $stock->quantity >= $product->quantity) {
                        $stock->decrement('quantity', $product->quantity);

                        StockTransaction::create([
                            'type' => 'disbursed',
                            'transaction_date' => now()->toDateString(),
                            'product_type_id' => $product->product_type_id,
                            'product_name' => $product->product_name,
                            'grammage' => $product->grammage,
                            'quantity' => $product->quantity,
                            'disbursed_to' => 'Order #'.$order->id.' delivery',
                            'user_id' => $processor->id,
                        ]);
                    }
                }
            }

            $creator = $order->user;
            if ($creator) {
                $creator->increment('stock_balance', $order->total_price);
            }

            $order->update([
                'status' => OrderStatus::Delivered,
                'assignment_status' => AssignmentStatus::Delivered,
            ]);
        });
    }
}
