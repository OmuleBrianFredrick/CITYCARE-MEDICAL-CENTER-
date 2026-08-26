<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PatientPortalEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        string $id,
        private readonly string $eventKey,
        private readonly string $title,
        private readonly string $message,
        private readonly string $url,
        private readonly array $context = [],
    ) {
        $this->id = $id;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'patient-portal-event';
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_key' => $this->eventKey,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'context' => $this->context,
        ];
    }
}
