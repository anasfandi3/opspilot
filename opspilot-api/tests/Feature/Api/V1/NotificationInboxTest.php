<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\RequestApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_lists_only_owned_notifications_newest_first_with_filters_and_pagination(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $oldest = $this->record($user, 'request_approved', now()->subMinutes(3), now()->subMinute());
        $middle = $this->record($user, 'comment_added', now()->subMinutes(2));
        $newest = $this->record($user, 'approval_assigned', now()->subMinute());
        $this->record($other, 'request_rejected', now());
        $this->authenticate($user);

        $this->getJson('/api/v1/notifications?per_page=2')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonMissingPath('data.0.type')
            ->assertJsonMissingPath('data.0.notifiable_type')
            ->assertJsonMissingPath('data.0.notifiable_id');
        $this->getJson('/api/v1/notifications?status=unread')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $newest->id);
        $this->getJson('/api/v1/notifications?status=read')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $oldest->id);
        $this->getJson('/api/v1/notifications?status=invalid')->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->getJson('/api/v1/notifications?per_page=101')->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_unread_count_read_unread_and_read_all_are_owned_and_idempotent(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $first = $this->record($user, 'approval_assigned', now()->subMinute());
        $second = $this->record($user, 'comment_added', now());
        $foreign = $this->record($other, 'request_rejected', now());
        $this->authenticate($user);

        $this->getJson('/api/v1/notifications/unread-count')->assertOk()
            ->assertJsonPath('data.unread_count', 2);
        $this->patchJson("/api/v1/notifications/{$first->id}/read")->assertOk()
            ->assertJsonPath('data.id', $first->id);
        $firstReadAt = $first->fresh()->read_at;
        $this->patchJson("/api/v1/notifications/{$first->id}/read")->assertOk();
        $this->assertTrue($firstReadAt->equalTo($first->fresh()->read_at));
        $this->patchJson("/api/v1/notifications/{$first->id}/unread")->assertOk()
            ->assertJsonPath('data.read_at', null);
        $this->patchJson("/api/v1/notifications/{$first->id}/unread")->assertOk();
        $this->postJson('/api/v1/notifications/read-all')->assertOk()
            ->assertJsonPath('data.affected', 2);
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
        $this->patchJson("/api/v1/notifications/{$foreign->id}/read")->assertNotFound();
    }

    public function test_database_channel_uses_stable_safe_payload_and_event_type(): void
    {
        $user = User::factory()->create();
        $payload = $this->payload('request_approved');

        Notification::sendNow($user, new RequestApprovedNotification($payload), ['database']);

        $notification = $user->notifications()->sole();
        $this->assertSame('request_approved', $notification->type);
        $this->assertSame($payload, $notification->data);
        $this->assertArrayNotHasKey('payload', $notification->data['request']);
        $this->assertArrayNotHasKey('definition_snapshot', $notification->data['request']);
        $this->authenticate($user);
        $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.0.event', 'request_approved')
            ->assertJsonPath('data.0.workspace.id', 4)
            ->assertJsonPath('data.0.request.id', 51);
    }

    public function test_notification_inbox_endpoints_require_authentication(): void
    {
        $user = User::factory()->create();
        $notification = $this->record($user, 'request_approved', now());
        $this->withToken('invalid');

        foreach ([
            $this->getJson('/api/v1/notifications'),
            $this->getJson('/api/v1/notifications/unread-count'),
            $this->patchJson("/api/v1/notifications/{$notification->id}/read"),
            $this->patchJson("/api/v1/notifications/{$notification->id}/unread"),
            $this->postJson('/api/v1/notifications/read-all'),
        ] as $response) {
            $response->assertUnauthorized();
        }
    }

    private function record(
        User $user,
        string $event,
        \DateTimeInterface $createdAt,
        ?\DateTimeInterface $readAt = null,
    ): DatabaseNotification {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $event,
            'data' => $this->payload($event),
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(string $event): array
    {
        return [
            'event' => $event,
            'message' => 'Notification message.',
            'workspace' => ['id' => 4, 'name' => 'Acme Operations'],
            'request' => [
                'id' => 51,
                'request_type' => ['id' => 6, 'name' => 'Purchase Request'],
            ],
        ];
    }

    private function authenticate(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken);
    }
}
