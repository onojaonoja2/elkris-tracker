<?php

namespace App\Notifications;

use App\Channels\SnsSmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AccountStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $action,
        public string $managerName,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->phone && $notifiable->sms_notifications) {
            $channels[] = SnsSmsChannel::class;
        }

        return $channels;
    }

    public function toSnsSms(object $notifiable): string
    {
        $message = "Your account has been {$this->action} by {$this->managerName}.";

        if ($this->reason) {
            $message .= " Reason: {$this->reason}";
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account_status',
            'title' => 'Account '.ucfirst($this->action),
            'message' => "Your account has been {$this->action} by {$this->managerName}."
                .($this->reason ? " Reason: {$this->reason}" : ''),
            'resource_id' => $notifiable->id,
            'resource_type' => 'User',
        ];
    }
}
