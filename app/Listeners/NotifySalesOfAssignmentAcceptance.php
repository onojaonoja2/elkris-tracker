<?php

namespace App\Listeners;

use App\Events\OrderAssignmentAccepted;
use App\Notifications\NewSubmissionNotification;

class NotifySalesOfAssignmentAcceptance
{
    public function handle(OrderAssignmentAccepted $event): void
    {
        $order = $event->order;

        if ($order->assignedBy) {
            $order->assignedBy->notify(new NewSubmissionNotification(
                type: 'order_assignment_accepted',
                title: 'Order Assignment Accepted',
                message: "{$order->assignedTo?->name} has accepted Order #{$order->id}.",
                resourceId: $order->id,
                resourceType: 'Order',
            ));
        }
    }
}
