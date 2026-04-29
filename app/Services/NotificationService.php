<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\NewSubmissionNotification;

class NotificationService
{
    public static function notifyAdmins(string $type, string $title, string $message, ?int $resourceId = null, ?string $resourceType = null): void
    {
        $admins = User::whereIn('role', ['admin', 'manager'])->get();
        foreach ($admins as $admin) {
            $admin->notify(new NewSubmissionNotification($type, $title, $message, $resourceId, $resourceType));
        }
    }

    public static function notifyTeamLead(int $leadId, string $type, string $title, string $message, ?int $resourceId = null, ?string $resourceType = null): void
    {
        $lead = User::find($leadId);
        if ($lead) {
            $lead->notify(new NewSubmissionNotification($type, $title, $message, $resourceId, $resourceType));
        }
    }

    public static function notifySupervisors(string $type, string $title, string $message, ?int $resourceId = null, ?string $resourceType = null): void
    {
        $supervisors = User::where('role', 'supervisor')->get();
        foreach ($supervisors as $supervisor) {
            $supervisor->notify(new NewSubmissionNotification($type, $title, $message, $resourceId, $resourceType));
        }
    }

    public static function notifyRep(int $repId, string $type, string $title, string $message, ?int $resourceId = null, ?string $resourceType = null): void
    {
        $rep = User::find($repId);
        if ($rep) {
            $rep->notify(new NewSubmissionNotification($type, $title, $message, $resourceId, $resourceType));
        }
    }
}
