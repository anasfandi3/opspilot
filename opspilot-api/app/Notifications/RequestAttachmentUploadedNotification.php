<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class RequestAttachmentUploadedNotification extends RequestWorkflowNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return $this->mail('Attachment uploaded to request')
            ->line('Uploaded by: '.$this->payload['actor']['name'])
            ->line('File: '.$this->payload['attachment']['original_name']);
    }
}
