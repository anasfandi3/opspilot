<?php

namespace Tests\Feature\Api\V1;

use App\Models\RequestSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpsPilotEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_product_journey_across_public_api_boundaries(): void
    {
        Notification::fake();
        Storage::fake('local');
        $owner = $this->register('Owner', 'e2e-owner@example.com');
        $requester = $this->register('Requester', 'e2e-requester@example.com');
        $approver = $this->register('Approver', 'e2e-approver@example.com');

        $this->authenticate($owner['token']);
        $workspaceId = $this->postJson('/api/v1/workspaces', ['name' => 'E2E Operations'])
            ->assertCreated()->json('data.id');
        foreach ([[$requester, 'requester'], [$approver, 'approver']] as [$invitee, $role]) {
            $this->authenticate($owner['token']);
            $token = $this->postJson("/api/v1/workspaces/{$workspaceId}/invitations", [
                'email' => $invitee['email'],
                'role' => $role,
            ])->assertCreated()->json('data.token');
            $this->authenticate($invitee['token']);
            $this->postJson("/api/v1/invitations/{$token}/accept")->assertOk();
        }

        $this->authenticate($owner['token']);
        $typeId = $this->postJson("/api/v1/workspaces/{$workspaceId}/request-types", [
            'name' => 'Purchase Request',
            'description' => 'E2E request type',
        ])->assertCreated()->json('data.id');
        $fieldId = $this->postJson("/api/v1/workspaces/{$workspaceId}/request-types/{$typeId}/fields", [
            'key' => 'amount',
            'label' => 'Amount',
            'type' => 'decimal',
            'is_required' => true,
            'config' => ['min' => 0],
        ])->assertCreated()->json('data.id');
        $workflowId = $this->postJson("/api/v1/workspaces/{$workspaceId}/request-types/{$typeId}/workflows", [
            'name' => 'Purchase approval',
        ])->assertCreated()->json('data.id');
        $stepsUrl = "/api/v1/workspaces/{$workspaceId}/request-types/{$typeId}/workflows/{$workflowId}/steps";
        $this->postJson($stepsUrl, [
            'name' => 'Owner review',
            'approver_type' => 'role',
            'approver_role' => 'owner',
            'conditions' => [],
        ])->assertCreated();
        $this->postJson($stepsUrl, [
            'name' => 'Finance review',
            'approver_type' => 'role',
            'approver_role' => 'approver',
            'conditions' => [[
                'field_id' => $fieldId,
                'operator' => 'greater_than_or_equal',
                'value' => 100,
            ]],
        ])->assertCreated();
        $this->postJson("/api/v1/workspaces/{$workspaceId}/request-types/{$typeId}/workflows/{$workflowId}/publish")
            ->assertOk()->assertJsonPath('data.status', 'active');

        $this->authenticate($requester['token']);
        $this->getJson("/api/v1/workspaces/{$workspaceId}/request-catalog")
            ->assertOk()->assertJsonPath('data.0.id', $typeId);
        $submissionId = $this->postJson("/api/v1/workspaces/{$workspaceId}/request-types/{$typeId}/requests")
            ->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
        $requestUrl = "/api/v1/workspaces/{$workspaceId}/requests/{$submissionId}";
        $this->patchJson($requestUrl, ['payload' => ['amount' => 250]])
            ->assertOk()->assertJsonPath('data.payload.amount', 250);
        $this->postJson($requestUrl.'/submit')->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->authenticate($owner['token']);
        $ownerApprovalId = $this->getJson("/api/v1/workspaces/{$workspaceId}/approvals")
            ->assertOk()->assertJsonCount(1, 'data')->json('data.0.id');
        $this->postJson("/api/v1/workspaces/{$workspaceId}/approvals/{$ownerApprovalId}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->authenticate($approver['token']);
        $financeApprovalId = $this->getJson("/api/v1/workspaces/{$workspaceId}/approvals")
            ->assertOk()->assertJsonCount(1, 'data')->json('data.0.id');
        $this->postJson("/api/v1/workspaces/{$workspaceId}/approvals/{$financeApprovalId}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->authenticate($requester['token']);
        $this->getJson($requestUrl)->assertOk()->assertJsonPath('data.status', 'approved');
        $this->postJson($requestUrl.'/comments', ['body' => 'Purchase completed.'])->assertCreated();
        $attachmentId = $this->post($requestUrl.'/attachments', [
            'file' => UploadedFile::fake()->create('receipt.txt', 1, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->get($requestUrl."/attachments/{$attachmentId}/download")->assertOk();
        $this->getJson($requestUrl.'/activity')->assertOk()->assertJsonCount(9, 'data');

        $requesterModel = User::query()->findOrFail($requester['id']);
        $requesterModel->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'request_approved',
            'data' => [
                'event' => 'request_approved',
                'message' => 'Purchase Request was approved.',
                'workspace' => ['id' => $workspaceId, 'name' => 'E2E Operations'],
                'request' => ['id' => $submissionId, 'request_type' => ['id' => $typeId, 'name' => 'Purchase Request']],
            ],
        ]);
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.0.event', 'request_approved');

        $this->authenticate($owner['token']);
        $this->getJson("/api/v1/workspaces/{$workspaceId}/dashboard")
            ->assertOk()->assertJsonPath('data.requests.approved', 1);
        $this->getJson("/api/v1/workspaces/{$workspaceId}/reports/requests")
            ->assertOk()->assertJsonPath('data.created.total', 1);
        $this->getJson("/api/v1/workspaces/{$workspaceId}/reports/approvals")
            ->assertOk()->assertJsonPath('data.decisions.total', 2);
        $this->assertSame('approved', RequestSubmission::query()->findOrFail($submissionId)->status->value);
    }

    /** @return array{id: int, email: string, token: string} */
    private function register(string $name, string $email): array
    {
        $data = $this->postJson('/api/v1/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => 'E2E test',
        ])->assertCreated()->json('data');

        return ['id' => $data['user']['id'], 'email' => $data['user']['email'], 'token' => $data['token']];
    }

    private function authenticate(string $token): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($token);
    }
}
