<?php

namespace App\Listeners;

use App\Events\OrderDelivered;
use App\Services\NotificationService;

class NotifyAdminsOfDelivery
{
    public function handle(OrderDelivered $event): void
    {
        $order = $event->order;

        NotificationService::notifyAdmins(
            'order_delivered',
            'Order Delivered',
            "Order #{$order->id} has been delivered",
            $order->id,
            'order'
        );
    }
}
