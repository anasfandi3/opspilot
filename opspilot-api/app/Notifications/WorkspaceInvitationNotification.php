<?php

namespace App\Notifications;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WorkspaceInvitation $invitation, public string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Workspace invitation')
            ->line("You have been invited to {$this->invitation->workspace->name}.")
            // Backend placeholder: a mail link cannot invoke the authenticated POST endpoint directly.
            // A future frontend acceptance page should submit the token after the user authenticates.
            ->action('Accept invitation', url('/api/v1/invitations/'.$this->token.'/accept'))
            ->line('This invitation expires on '.$this->invitation->expires_at->toDayDateTimeString().'.');
    }
}
