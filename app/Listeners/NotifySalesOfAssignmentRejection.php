<?php

namespace App\Listeners;

use App\Events\OrderAssignmentRejected;
use App\Notifications\NewSubmissionNotification;

class NotifySalesOfAssignmentRejection
{
    public function handle(OrderAssignmentRejected $event): void
    {
        $order = $event->order;

        if ($order->assignedBy) {
            $order->assignedBy->notify(new NewSubmissionNotification(
                type: 'order_assignment_rejected',
                title: 'Order Assignment Rejected',
                message: "Order #{$order->id} assignment has been rejected and returned to the pending pool.",
                resourceId: $order->id,
                resourceType: 'Order',
            ));
        }
    }
}
