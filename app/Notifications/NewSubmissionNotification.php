<?php

namespace App\Notifications;

use App\Channels\SnsSmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewSubmissionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $title,
        public string $message,
        public ?int $resourceId = null,
        public ?string $resourceType = null
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
        return "[{$this->type}] {$this->title}: {$this->message}";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'resource_id' => $this->resourceId,
            'resource_type' => $this->resourceType,
        ];
    }
}
