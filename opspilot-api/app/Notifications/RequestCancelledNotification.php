<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class RequestCancelledNotification extends RequestWorkflowNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return $this->mail('Request cancelled');
    }
}
