<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\RequestApprovedNotification;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesDisposableSqliteDatabase;
use Tests\TestCase;

class QueueSmokeTest extends TestCase
{
    use UsesDisposableSqliteDatabase;

    public function test_database_and_mail_notification_jobs_are_processed_without_failures(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'array']);

        $user = User::factory()->create();
        $user->notify(new RequestApprovedNotification([
            'event' => 'request_approved',
            'message' => 'Purchase Request #1 was approved.',
            'workspace' => ['id' => 1, 'name' => 'Operations'],
            'request' => ['id' => 1, 'request_type' => ['id' => 2, 'name' => 'Purchase Request']],
        ]));

        $this->assertSame(2, DB::table('jobs')->count());

        $this->artisan('queue:work', [
            '--stop-when-empty' => true,
            '--tries' => 1,
        ])->assertExitCode(0);

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => 'request_approved',
        ]);
    }
}
