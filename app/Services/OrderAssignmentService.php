<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Events\OrderAssigned;
use App\Events\OrderAssignmentAccepted;
use App\Events\OrderAssignmentRejected;
use App\Models\AgentStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OrderAssignmentService
{
    /**
     * Builds the query used to locate an agent's stock for a given order
     * product, matching by product type when available and falling back to
     * the product name.
     */
    public static function stockQueryForProduct(User $user, Product $product): Builder
    {
        return AgentStock::query()
            ->where('user_id', $user->id)
            ->where(function (Builder $query) use ($product) {
                $query->where('product_type_id', $product->product_type_id)
                    ->orWhere('product_name', $product->product_name);
            })
            ->where('grammage', $product->grammage);
    }

    /**
     * Determines whether an agent currently holds enough stock to deliver
     * every item on an order.
     */
    public static function hasSufficientStock(User $user, Order $order): bool
    {
        foreach ($order->products as $product) {
            $stock = static::stockQueryForProduct($user, $product)->first();

            if (! $stock || $stock->quantity < $product->quantity) {
                return false;
            }
        }

        return true;
    }

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

    /**
     * Replace an existing payment proof with a corrected upload.
     * The previous file is removed from storage.
     */
    public static function replacePaymentProof(Order $order, string $path, int $uploadedBy): void
    {
        DB::transaction(function () use ($order, $path, $uploadedBy) {
            if (! $order->hasPaymentProof()) {
                throw ValidationException::withMessages([
                    'payment_proof_path' => 'No payment proof has been uploaded yet.',
                ]);
            }

            $oldPath = $order->payment_proof_path;

            $order->update([
                'payment_proof_path' => $path,
                'payment_proof_uploaded_by' => $uploadedBy,
                'payment_proof_uploaded_at' => now(),
            ]);

            if ($oldPath && $oldPath !== $path) {
                Storage::disk('s3')->delete($oldPath);
            }
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
                    $stock = static::stockQueryForProduct($processor, $product)
                        ->lockForUpdate()
                        ->first();

                    if (! $stock || $stock->quantity < $product->quantity) {
                        $available = $stock?->quantity ?? 0;
                        throw ValidationException::withMessages([
                            'stock' => "Insufficient stock for {$product->product_name} ({$product->grammage}g). Available: {$available}, required: {$product->quantity}.",
                        ]);
                    }
                }

                foreach ($order->products as $product) {
                    $stock = static::stockQueryForProduct($processor, $product)
                        ->lockForUpdate()
                        ->first();

                    $stock?->decrement('quantity', $product->quantity);

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

                $processor->increment('stock_balance', $order->total_price);
            }

            $order->update([
                'status' => OrderStatus::Delivered,
                'assignment_status' => AssignmentStatus::Delivered,
            ]);
        });
    }

    /**
     * Mark any order as delivered through the proper delivery flow.
     *
     * Assigned orders are routed to the CSR or sales confirmation path based
     * on the assignee's role, keeping status and assignment status in sync so
     * every dashboard reflects the delivery. Unassigned orders must name the
     * sales personnel who handled the delivery; the order is assigned to them
     * before the sales confirmation path runs.
     */
    public static function markOrderDelivered(Order $order, ?User $salesHandler = null, ?int $actorId = null): void
    {
        $order->refresh();

        if ($order->status === OrderStatus::Delivered && $order->assignment_status === AssignmentStatus::Delivered) {
            throw ValidationException::withMessages([
                'status' => 'This order has already been marked as delivered.',
            ]);
        }

        if (! $order->hasPaymentProof()) {
            throw ValidationException::withMessages([
                'payment_proof' => 'A payment proof must be uploaded before this order can be marked as delivered.',
            ]);
        }

        $assignee = $order->assignedTo;

        if ($assignee) {
            if ($assignee->hasRole('community_sales_representative')) {
                static::confirmDeliveryByCsr($order);
            } else {
                static::confirmDeliveryBySales($order);
            }

            return;
        }

        if (! $salesHandler || ! $salesHandler->hasRole('sales')) {
            throw ValidationException::withMessages([
                'delivered_by_sales' => 'Select the sales personnel who handled the delivery.',
            ]);
        }

        DB::transaction(function () use ($order, $salesHandler, $actorId) {
            $order->update([
                'assigned_to' => $salesHandler->id,
                'assigned_by' => $actorId ?? auth()->id(),
                'assigned_at' => now(),
                'assignment_status' => AssignmentStatus::Accepted,
            ]);
        });

        static::confirmDeliveryBySales($order->fresh());
    }

    /**
     * Sync helper for order edit forms: when an edit leaves an order with a
     * delivered status but an out-of-sync assignment status, run it through
     * the delivery flow. Migrated and already-synced orders are no-ops.
     */
    public static function completeDeliveredEdit(Order $order, mixed $handlerId): void
    {
        $order->refresh();

        if ($order->is_migrated_order
            || $order->status !== OrderStatus::Delivered
            || $order->assignment_status === AssignmentStatus::Delivered) {
            return;
        }

        $handler = filled($handlerId) ? User::find($handlerId) : null;

        static::markOrderDelivered($order, $handler, auth()->id());
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
                    $stock = static::stockQueryForProduct($processor, $product)
                        ->lockForUpdate()
                        ->first();

                    if (! $stock || $stock->quantity < $product->quantity) {
                        $available = $stock?->quantity ?? 0;
                        throw ValidationException::withMessages([
                            'stock' => "Insufficient stock for {$product->product_name} ({$product->grammage}g). Available: {$available}, required: {$product->quantity}.",
                        ]);
                    }
                }

                foreach ($order->products as $product) {
                    $stock = static::stockQueryForProduct($processor, $product)
                        ->lockForUpdate()
                        ->first();

                    $stock?->decrement('quantity', $product->quantity);

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
