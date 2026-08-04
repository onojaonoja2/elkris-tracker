<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\NewSubmissionNotification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public static function notifyAdmins(string $type, string $title, string $message, ?int $resourceId = null, ?string $resourceType = null): void
    {
        $admins = User::whereIn('role', ['admin', 'manager'])->get();
        foreach ($admins as $admin) {
            try {
                $admin->notify(new NewSubmissionNotification($type, $title, $message, $resourceId, $resourceType));
            } catch (\Throwable $e) {
                Log::error("Failed to send notification to admin {$admin->id}: {$e->getMessage()}");
            }
        }
    }

    public static function notifyUser(int $userId, string $type, string $title, string $message, ?int $resourceId = null, ?string $resourceType = null): void
    {
        $user = User::find($userId);
        if ($user) {
            try {
                $user->notify(new NewSubmissionNotification($type, $title, $message, $resourceId, $resourceType));
            } catch (\Throwable $e) {
                Log::error("Failed to send notification to user {$userId}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Notify all users holding any of the given roles.
     *
     * @param  array<int, string>  $roles
     */
    public static function notifyRoles(array $roles, string $type, string $title, string $message, ?int $resourceId = null, ?string $resourceType = null): void
    {
        $users = User::whereIn('role', $roles)->get();

        foreach ($users as $user) {
            try {
                $user->notify(new NewSubmissionNotification($type, $title, $message, $resourceId, $resourceType));
            } catch (\Throwable $e) {
                Log::error("Failed to send notification to user {$user->id}: {$e->getMessage()}");
            }
        }
    }
}
