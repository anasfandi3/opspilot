<?php

namespace Tests\Feature\Api\V1;

use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\RequestStatus;
use App\Enums\WorkspaceRole;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\WorkspacePermissions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_date_range_defaults_validation_limits_and_utc_exclusive_boundary(): void
    {
        CarbonImmutable::setTestNow('2026-08-09 12:00:00 UTC');
        [$owner, $workspace, $type] = $this->definition();
        $this->authenticate($owner, $workspace);
        $url = $this->url($workspace);

        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.period.from', '2026-07-11')
            ->assertJsonPath('data.period.to', '2026-08-09')
            ->assertJsonPath('data.period.timezone', 'UTC');
        $this->getJson($url.'?from=2026-08-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson($url.'?to=2026-08-01')->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->getJson($url.'?from=not-a-date&to=2026-08-01')->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->getJson($url.'?from=2026-08-02&to=2026-08-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson($url.'?from=2025-08-09&to=2026-08-09')->assertOk();
        $this->getJson($url.'?from=2025-08-08&to=2026-08-09')->assertUnprocessable()->assertJsonValidationErrors('to');

        $this->submission($workspace, $type, $owner, RequestStatus::Draft, '2026-08-09 23:59:59');
        $this->submission($workspace, $type, $owner, RequestStatus::Draft, '2026-08-10 00:00:00');
        $this->getJson($url.'?from=2026-08-09&to=2026-08-09')->assertOk()
            ->assertJsonPath('data.created.total', 1)
            ->assertJsonPath('data.created.trend.0.date', '2026-08-09')
            ->assertJsonPath('data.created.trend.0.count', 1);
    }

    public function test_request_report_separates_created_cohort_from_lifecycle_throughput_and_calculates_averages(): void
    {
        [$owner, $workspace, $typeA] = $this->definition();
        $typeB = $this->requestType($workspace, $owner, 'Facilities');
        $this->submission($workspace, $typeA, $owner, RequestStatus::Approved, '2026-08-01 09:00:00', '2026-07-31 10:00:00', resolvedAt: '2026-08-01 10:00:00');
        $this->submission($workspace, $typeA, $owner, RequestStatus::Draft, '2026-08-01 11:00:00');
        $this->submission($workspace, $typeB, $owner, RequestStatus::Rejected, '2026-08-03 09:00:00', '2026-08-02 10:00:00', resolvedAt: '2026-08-04 10:00:00');
        $this->submission($workspace, $typeA, $owner, RequestStatus::Approved, '2026-06-01 09:00:00', '2026-08-04 10:00:00', resolvedAt: '2026-08-04 22:00:00');
        $this->submission($workspace, $typeA, $owner, RequestStatus::Approved, '2026-08-02 09:00:00', '2026-08-02 10:00:00', resolvedAt: '2026-08-11 10:00:00');
        $this->submission($workspace, $typeB, $owner, RequestStatus::Cancelled, '2026-08-03 12:00:00', '2026-08-03 13:00:00', cancelledAt: '2026-08-05 10:00:00');

        $foreignOwner = User::factory()->create();
        $foreignWorkspace = $this->workspace($foreignOwner);
        $foreignType = $this->requestType($foreignWorkspace, $foreignOwner, 'Foreign');
        $this->submission($foreignWorkspace, $foreignType, $foreignOwner, RequestStatus::Approved, '2026-08-01 09:00:00', '2026-08-01 09:00:00', resolvedAt: '2026-08-01 10:00:00');

        $this->authenticate($owner, $workspace);
        $response = $this->getJson($this->url($workspace).'?from=2026-08-01&to=2026-08-05')
            ->assertOk()
            ->assertJsonPath('data.created.total', 5)
            ->assertJsonPath('data.created.current_status.draft', 1)
            ->assertJsonPath('data.created.current_status.submitted', 0)
            ->assertJsonPath('data.created.current_status.approved', 2)
            ->assertJsonPath('data.created.current_status.rejected', 1)
            ->assertJsonPath('data.created.current_status.cancelled', 1)
            ->assertJsonPath('data.created.by_request_type.0.request_type.id', $typeA->id)
            ->assertJsonPath('data.created.by_request_type.0.count', 3)
            ->assertJsonPath('data.created.by_request_type.1.request_type.id', $typeB->id)
            ->assertJsonPath('data.created.by_request_type.1.count', 2)
            ->assertJsonCount(5, 'data.created.trend')
            ->assertJsonPath('data.created.trend.0.count', 2)
            ->assertJsonPath('data.created.trend.1.count', 1)
            ->assertJsonPath('data.created.trend.2.count', 2)
            ->assertJsonPath('data.created.trend.3.count', 0)
            ->assertJsonPath('data.lifecycle.submitted', 4)
            ->assertJsonPath('data.lifecycle.approved', 2)
            ->assertJsonPath('data.lifecycle.rejected', 1)
            ->assertJsonPath('data.lifecycle.cancelled', 1)
            ->assertJsonPath('data.lifecycle.resolution.count', 3)
            ->assertJsonPath('data.lifecycle.resolution.average_hours', 28)
            ->assertJsonPath('data.lifecycle.resolution.approved_average_hours', 18)
            ->assertJsonPath('data.lifecycle.resolution.rejected_average_hours', 48);

        $this->assertSame(5, count($response->json('data.created.trend')));
    }

    public function test_request_type_filter_is_tenant_scoped_and_empty_resolution_averages_are_null(): void
    {
        [$owner, $workspace, $typeA] = $this->definition();
        $typeB = $this->requestType($workspace, $owner, 'Facilities');
        $this->submission($workspace, $typeA, $owner, RequestStatus::Draft, '2026-08-01 09:00:00');
        $this->submission($workspace, $typeB, $owner, RequestStatus::Draft, '2026-08-01 10:00:00');
        $foreignOwner = User::factory()->create();
        $foreignWorkspace = $this->workspace($foreignOwner);
        $foreignType = $this->requestType($foreignWorkspace, $foreignOwner, 'Foreign');
        $this->authenticate($owner, $workspace);
        $url = $this->url($workspace).'?from=2026-08-01&to=2026-08-02&request_type_id=';

        $this->getJson($url.$typeA->id)->assertOk()
            ->assertJsonPath('data.created.total', 1)
            ->assertJsonCount(1, 'data.created.by_request_type')
            ->assertJsonPath('data.lifecycle.resolution.count', 0)
            ->assertJsonPath('data.lifecycle.resolution.average_hours', null)
            ->assertJsonPath('data.lifecycle.resolution.approved_average_hours', null)
            ->assertJsonPath('data.lifecycle.resolution.rejected_average_hours', null);
        $this->getJson($url.$foreignType->id)->assertUnprocessable()->assertJsonValidationErrors('request_type_id');
    }

    /** @return array{User, Workspace, RequestType} */
    private function definition(): array
    {
        $owner = User::factory()->create();
        $workspace = $this->workspace($owner);

        return [$owner, $workspace, $this->requestType($workspace, $owner, 'Purchasing')];
    }

    private function workspace(User $owner): Workspace
    {
        $workspace = Workspace::factory()->create(['owner_id' => $owner]);
        $this->member($workspace, $owner, WorkspaceRole::Owner);

        return $workspace;
    }

    private function member(Workspace $workspace, User $user, WorkspaceRole $role): void
    {
        app(SynchronizeWorkspacePermissions::class)->handle($workspace);
        WorkspaceMembership::query()->firstOrCreate(['workspace_id' => $workspace->id, 'user_id' => $user->id], ['joined_at' => now()]);
        app(WorkspacePermissions::class)->assign($user, $workspace, $role);
    }

    private function requestType(Workspace $workspace, User $creator, string $name): RequestType
    {
        return RequestType::factory()->create([
            'workspace_id' => $workspace,
            'created_by' => $creator,
            'name' => $name,
        ]);
    }

    private function submission(
        Workspace $workspace,
        RequestType $type,
        User $creator,
        RequestStatus $status,
        string $createdAt,
        ?string $submittedAt = null,
        ?string $cancelledAt = null,
        ?string $resolvedAt = null,
    ): RequestSubmission {
        return RequestSubmission::factory()->create([
            'workspace_id' => $workspace,
            'request_type_id' => $type,
            'created_by' => $creator,
            'status' => $status,
            'created_at' => $createdAt,
            'submitted_at' => $submittedAt,
            'cancelled_at' => $cancelledAt,
            'resolved_at' => $resolvedAt,
        ]);
    }

    private function authenticate(User $user, Workspace $workspace): void
    {
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        $this->withToken($user->createToken('test')->plainTextToken);
    }

    private function url(Workspace $workspace): string
    {
        return "/api/v1/workspaces/{$workspace->id}/reports/requests";
    }
}
