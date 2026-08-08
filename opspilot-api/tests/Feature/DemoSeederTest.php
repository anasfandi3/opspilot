<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\WorkflowStatus;
use App\Enums\WorkspaceRole;
use App\Models\RequestActivity;
use App\Models\RequestApproval;
use App\Models\RequestComment;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Support\ApprovalReportService;
use App\Support\ReportDateRange;
use App\Support\RequestReportService;
use App\Support\WorkspaceDashboardService;
use App\Support\WorkspacePermissions;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_builds_a_repeatable_product_showcase_without_operational_queue_rows(): void
    {
        $this->seed(DemoSeeder::class);
        $workspace = Workspace::query()->where('slug', 'acme-operations')->firstOrFail();
        $roles = [
            'owner@opspilot.test' => WorkspaceRole::Owner,
            'admin@opspilot.test' => WorkspaceRole::Admin,
            'approver@opspilot.test' => WorkspaceRole::Approver,
            'requester@opspilot.test' => WorkspaceRole::Requester,
            'auditor@opspilot.test' => WorkspaceRole::Auditor,
        ];
        foreach ($roles as $email => $role) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->assertTrue(Hash::check('password', $user->password));
            $this->assertSame($workspace->id, $user->current_workspace_id);
            $this->assertSame($role, app(WorkspacePermissions::class)->role($user, $workspace));
        }

        $this->assertSame(5, $workspace->memberships()->count());
        $this->assertSame(3, RequestType::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(3, Workflow::query()->where('workspace_id', $workspace->id)->where('status', WorkflowStatus::Active)->count());
        $this->assertSame(7, RequestSubmission::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame([
            RequestStatus::Approved->value => 2,
            RequestStatus::Cancelled->value => 1,
            RequestStatus::Draft->value => 1,
            RequestStatus::Rejected->value => 1,
            RequestStatus::Submitted->value => 2,
        ], RequestSubmission::query()->where('workspace_id', $workspace->id)
            ->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->map(fn ($count): int => (int) $count)->all());
        $this->assertSame(18, RequestApproval::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(3, RequestComment::query()->where('workspace_id', $workspace->id)->count());
        $this->assertGreaterThan(20, RequestActivity::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('jobs')->count());

        $range = ReportDateRange::fromFilters([]);
        $dashboard = app(WorkspaceDashboardService::class)->get($workspace);
        $requestReport = app(RequestReportService::class)->get($workspace, $range, []);
        $approvalReport = app(ApprovalReportService::class)->get($workspace, $range, []);
        $this->assertSame(7, $dashboard['requests']['total']);
        $this->assertSame(7, $requestReport['created']['total']);
        $this->assertSame(6, $approvalReport['decisions']['total']);

        $this->seed(DemoSeeder::class);
        $this->assertSame(1, Workspace::query()->where('slug', 'acme-operations')->count());
        $this->assertSame(7, RequestSubmission::query()->where('workspace_id', $workspace->id)->count());
    }

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        try {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('DemoSeeder is disabled in production.');

            app(DemoSeeder::class)->run();
        } finally {
            $this->app->detectEnvironment(fn (): string => 'testing');
        }
    }
}
