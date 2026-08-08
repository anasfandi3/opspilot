<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ApprovalAssignedNotification extends RequestWorkflowNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return $this->mail('Approval assigned')
            ->line('Workflow step: '.$this->payload['approval']['workflow_step_name']);
    }
}
