<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class RequestCommentAddedNotification extends RequestWorkflowNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return $this->mail('Comment added to request')
            ->line('Comment author: '.$this->payload['actor']['name']);
    }
}
