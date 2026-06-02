<?php

namespace App\Channels;

use Aws\Sns\SnsClient;
use Illuminate\Notifications\Notification;

class SnsSmsChannel
{
    protected SnsClient $sns;

    public function __construct()
    {
        $this->sns = new SnsClient([
            'version' => 'latest',
            'region' => config('services.sns.region'),
            'credentials' => [
                'key' => config('services.sns.key'),
                'secret' => config('services.sns.secret'),
            ],
        ]);
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->phone;
        if (! $phone) {
            return;
        }

        $message = $notification->toSnsSms($notifiable);
        if (empty($message)) {
            return;
        }

        $this->sns->publish([
            'Message' => $message,
            'PhoneNumber' => $phone,
            'MessageAttributes' => [
                'AWS.SNS.SMS.SenderID' => [
                    'DataType' => 'String',
                    'StringValue' => config('services.sns.sender_id'),
                ],
                'AWS.SNS.SMS.SMSType' => [
                    'DataType' => 'String',
                    'StringValue' => config('services.sns.sms_type'),
                ],
            ],
        ]);
    }
}
