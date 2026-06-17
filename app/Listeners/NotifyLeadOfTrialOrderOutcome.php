<?php

namespace App\Listeners;

use App\Events\TrialOrderApproved;
use App\Events\TrialOrderRejected;
use App\Services\NotificationService;

class NotifyLeadOfTrialOrderOutcome
{
    public function handle(TrialOrderApproved|TrialOrderRejected $event): void
    {
        $trialOrder = $event->trialOrder;
        $lead = $trialOrder->agent?->lead;

        if (! $lead) {
            return;
        }

        $status = $trialOrder->status->value;

        NotificationService::notifyUser(
            $lead->id,
            "trial_order_{$status}",
            'Trial Order '.ucfirst($status),
            "Trial order #{$trialOrder->id} has been {$status}",
            $trialOrder->id,
            'trial_order'
        );
    }
}
