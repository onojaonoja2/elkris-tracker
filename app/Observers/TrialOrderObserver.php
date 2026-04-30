<?php

namespace App\Observers;

use App\Models\TrialOrder;
use App\Services\NotificationService;

class TrialOrderObserver
{
    public function created(TrialOrder $trialOrder): void
    {
        NotificationService::notifyAdmins(
            'trial_order_submitted',
            'New Trial Order',
            "Trial order #{$trialOrder->id} requires approval",
            $trialOrder->id,
            'trial_order'
        );
    }

    public function updated(TrialOrder $trialOrder): void
    {
        if ($trialOrder->wasChanged('status') && $trialOrder->status === 'approved') {
            $lead = $trialOrder->customer->lead;
            if ($lead) {
                NotificationService::notifyRep(
                    $lead->id,
                    'trial_order_approved',
                    'Trial Order Approved',
                    "Trial order #{$trialOrder->id} has been approved",
                    $trialOrder->id,
                    'trial_order'
                );
            }
        }

        if ($trialOrder->wasChanged('status') && $trialOrder->status === 'rejected') {
            $lead = $trialOrder->customer->lead;
            if ($lead) {
                NotificationService::notifyRep(
                    $lead->id,
                    'trial_order_rejected',
                    'Trial Order Rejected',
                    "Trial order #{$trialOrder->id} has been rejected",
                    $trialOrder->id,
                    'trial_order'
                );
            }
        }
    }
}
