<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\RequestApprovedNotification;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesDisposableSqliteDatabase;
use Tests\TestCase;

class NotificationAfterCommitTest extends TestCase
{
    use UsesDisposableSqliteDatabase;

    public function test_database_notification_jobs_are_queued_only_after_commit_and_discarded_on_rollback(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'array']);
        $user = User::factory()->create();

        DB::beginTransaction();
        $user->notify(new RequestApprovedNotification($this->payload()));
        $this->assertSame(0, DB::table('jobs')->count());
        DB::rollBack();
        $this->assertSame(0, DB::table('jobs')->count());

        DB::beginTransaction();
        $user->notify(new RequestApprovedNotification($this->payload()));
        $this->assertSame(0, DB::table('jobs')->count());
        DB::commit();

        $this->assertSame(2, DB::table('jobs')->count());
        foreach (DB::table('jobs')->pluck('payload') as $payload) {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(RequestApprovedNotification::class, $decoded['displayName']);
        }

        DB::table('jobs')->delete();
        $user->delete();
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'event' => 'request_approved',
            'message' => 'Purchase Request #1 was approved.',
            'workspace' => ['id' => 1, 'name' => 'Operations'],
            'request' => ['id' => 1, 'request_type' => ['id' => 2, 'name' => 'Purchase Request']],
        ];
    }
}
