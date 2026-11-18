<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SocialNotification extends Notification
{
    /** @param array<string, int|string> $data */
    public function __construct(public array $data) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string> */
    public function toDatabase(object $notifiable): array
    {
        return $this->data;
    }
}
