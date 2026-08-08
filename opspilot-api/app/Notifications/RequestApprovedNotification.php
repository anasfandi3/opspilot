<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class RequestApprovedNotification extends RequestWorkflowNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return $this->mail('Request approved');
    }
}
