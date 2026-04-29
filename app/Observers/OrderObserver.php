<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\NotificationService;

class OrderObserver
{
    public function created(Order $order): void
    {
        $lead = $order->customer->lead;
        if ($lead) {
            NotificationService::notifyRep(
                $lead->id,
                'new_order',
                'New Order',
                "New order #{$order->id} from {$order->customer->name}",
                $order->id,
                'order'
            );
        }
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status')) {
            $lead = $order->customer->lead;
            if ($lead) {
                $statusMessages = [
                    'confirmed' => 'has been confirmed',
                    'processing' => 'is being processed',
                    'shipped' => 'has been shipped',
                    'delivered' => 'has been delivered',
                    'cancelled' => 'has been cancelled',
                ];

                $message = $statusMessages[$order->status] ?? "status changed to {$order->status}";
                NotificationService::notifyRep(
                    $lead->id,
                    'order_status_changed',
                    'Order Status Updated',
                    "Order #{$order->id} {$message}",
                    $order->id,
                    'order'
                );
            }

            if ($order->status === 'delivered') {
                NotificationService::notifyAdmins(
                    'order_delivered',
                    'Order Delivered',
                    "Order #{$order->id} has been delivered",
                    $order->id,
                    'order'
                );
            }
        }
    }
}
