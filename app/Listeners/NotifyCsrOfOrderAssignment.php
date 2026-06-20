<?php

namespace App\Listeners;

use App\Events\OrderAssigned;
use App\Notifications\NewSubmissionNotification;

class NotifyCsrOfOrderAssignment
{
    public function handle(OrderAssigned $event): void
    {
        $event->csr->notify(new NewSubmissionNotification(
            type: 'order_assignment',
            title: 'New Order Assigned',
            message: "You have been assigned Order #{$event->order->id} (₦".number_format($event->order->total_price, 2).'). Please review and confirm delivery.',
            resourceId: $event->order->id,
            resourceType: 'Order',
        ));
    }
}
