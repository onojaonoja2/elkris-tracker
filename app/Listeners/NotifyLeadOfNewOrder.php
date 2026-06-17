<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Services\NotificationService;

class NotifyLeadOfNewOrder
{
    public function handle(OrderCreated $event): void
    {
        $order = $event->order;
        $lead = $order->customer->lead;

        if ($lead) {
            NotificationService::notifyUser(
                $lead->id,
                'new_order',
                'New Order',
                "New order #{$order->id} from {$order->customer->name}",
                $order->id,
                'order'
            );
        }
    }
}
