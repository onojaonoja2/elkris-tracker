<?php

namespace App\Observers;

use App\Enums\TrialOrderStatus;
use App\Events\TrialOrderApproved;
use App\Events\TrialOrderRejected;
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
        if (! $trialOrder->wasChanged('status')) {
            return;
        }

        if ($trialOrder->status === TrialOrderStatus::Approved) {
            TrialOrderApproved::dispatch($trialOrder);
        }

        if ($trialOrder->status === TrialOrderStatus::Rejected) {
            TrialOrderRejected::dispatch($trialOrder);
        }
    }
}
