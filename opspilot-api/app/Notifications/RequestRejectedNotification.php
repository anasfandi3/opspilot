<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class RequestRejectedNotification extends RequestWorkflowNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        $mail = $this->mail('Request rejected')
            ->line('Workflow step: '.$this->payload['approval']['workflow_step_name']);

        return isset($this->payload['actor'])
            ? $mail->line('Rejected by: '.$this->payload['actor']['name'])
            : $mail;
    }
}
