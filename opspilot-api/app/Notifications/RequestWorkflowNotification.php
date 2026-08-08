<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class RequestWorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<string, mixed> $payload */
    public function __construct(protected array $payload)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    public function databaseType(object $notifiable): string
    {
        return (string) $this->payload['event'];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    protected function mail(string $subject): MailMessage
    {
        return (new MailMessage)
            ->subject($subject)
            ->line($this->payload['message'])
            ->line('Workspace: '.$this->payload['workspace']['name'])
            ->line('Request: '.$this->payload['request']['request_type']['name'].' #'.$this->payload['request']['id']);
    }
}
