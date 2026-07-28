<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Events\OrderDelivered;
use App\Models\Order;
use App\Services\NotificationService;

class OrderObserver
{
    public function created(Order $order): void
    {
        OrderCreated::dispatch($order);
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status')) {
            $order->load('customer.lead');
            $lead = $order->customer->lead;
            if ($lead) {
                $message = match ($order->status) {
                    OrderStatus::Dispatched => 'has been dispatched',
                    OrderStatus::Delivered => 'has been delivered',
                    OrderStatus::Cancelled => 'has been cancelled',
                    default => "status changed to {$order->status->getLabel()}",
                };

                NotificationService::notifyUser(
                    $lead->id,
                    'order_status_changed',
                    'Order Status Updated',
                    "Order #{$order->id} {$message}",
                    $order->id,
                    'order'
                );
            }

            if ($order->status === OrderStatus::Delivered) {
                OrderDelivered::dispatch($order);
            }
        }
    }
}
