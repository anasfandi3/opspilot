<?php

namespace Database\Seeders;

use App\Actions\ApproveRequestApproval;
use App\Actions\CancelRequest;
use App\Actions\CreateRequestComment;
use App\Actions\CreateRequestSubmission;
use App\Actions\CreateRequestType;
use App\Actions\CreateRequestTypeField;
use App\Actions\CreateWorkflow;
use App\Actions\CreateWorkspace;
use App\Actions\PublishWorkflow;
use App\Actions\RejectRequestApproval;
use App\Actions\SaveWorkflowStep;
use App\Actions\SubmitRequest;
use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\RequestApprovalStatus;
use App\Enums\WorkspaceRole;
use App\Models\RequestApproval;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\RequestTypeField;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\WorkspacePermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use LogicException;

class DemoSeeder extends Seeder
{
    /** @var array<string, User> */
    private array $users;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('DemoSeeder is disabled in production.');
        }

        if (Workspace::query()->where('slug', 'acme-operations')->exists()) {
            $this->command?->warn('The Acme Operations demo workspace already exists; no demo data was changed.');

            return;
        }

        Notification::fake();
        DB::transaction(function (): void {
            $this->users = $this->createUsers();
            $workspace = app(CreateWorkspace::class)->handle($this->users['owner'], 'Acme Operations');
            $this->addMembers($workspace);

            $purchase = $this->purchaseType($workspace);
            $leave = $this->leaveType($workspace);
            $equipment = $this->equipmentType($workspace);
            $this->createRequestHistory($workspace, $purchase, $leave, $equipment);
        });
    }

    /** @return array<string, User> */
    private function createUsers(): array
    {
        $users = [];
        foreach ([
            'owner' => ['Acme Owner', 'owner@opspilot.test'],
            'admin' => ['Acme Admin', 'admin@opspilot.test'],
            'approver' => ['Acme Approver', 'approver@opspilot.test'],
            'requester' => ['Acme Requester', 'requester@opspilot.test'],
            'auditor' => ['Acme Auditor', 'auditor@opspilot.test'],
        ] as $key => [$name, $email]) {
            $users[$key] = User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password'), 'email_verified_at' => now()],
            );
        }

        return $users;
    }

    private function addMembers(Workspace $workspace): void
    {
        app(SynchronizeWorkspacePermissions::class)->handle($workspace);
        foreach ([
            'admin' => WorkspaceRole::Admin,
            'approver' => WorkspaceRole::Approver,
            'requester' => WorkspaceRole::Requester,
            'auditor' => WorkspaceRole::Auditor,
        ] as $key => $role) {
            $user = $this->users[$key];
            WorkspaceMembership::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'joined_at' => now(),
            ]);
            app(WorkspacePermissions::class)->assign($user, $workspace, $role);
            $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        }
    }

    private function purchaseType(Workspace $workspace): RequestType
    {
        $type = $this->type($workspace, 'Purchase Request', 'Purchase goods and services with conditional review.');
        $item = $this->field($type, 'item_name', 'Item name', 'text', true);
        $cost = $this->field($type, 'estimated_cost', 'Estimated cost', 'decimal', true, ['min' => 0]);
        $category = $this->field($type, 'category', 'Category', 'select', true, [
            'options' => $this->options(['hardware', 'software', 'office', 'other']),
        ]);
        $this->field($type, 'justification', 'Justification', 'textarea', true);
        $this->field($type, 'urgent', 'Urgent', 'boolean');
        $workflow = $this->workflow($workspace, $type, 'Purchase approval');
        $this->step($workflow, 'Manager Approval', WorkspaceRole::Admin);
        $this->step($workflow, 'Finance Approval', WorkspaceRole::Approver, $cost, 'greater_than_or_equal', 100);
        $this->step($workflow, 'Director Approval', WorkspaceRole::Owner, $cost, 'greater_than_or_equal', 1000);
        $this->step($workflow, 'Technical Review', WorkspaceRole::Approver, $category, 'equals', 'hardware');
        app(PublishWorkflow::class)->handle($workflow);

        return $type;
    }

    private function leaveType(Workspace $workspace): RequestType
    {
        $type = $this->type($workspace, 'Leave Request', 'Request planned time away.');
        $this->field($type, 'start_date', 'Start date', 'date', true);
        $this->field($type, 'end_date', 'End date', 'date', true);
        $this->field($type, 'leave_type', 'Leave type', 'select', true, [
            'options' => $this->options(['annual', 'sick', 'unpaid']),
        ]);
        $this->field($type, 'reason', 'Reason', 'textarea', true);
        $workflow = $this->workflow($workspace, $type, 'Leave approval');
        $this->step($workflow, 'Manager Approval', WorkspaceRole::Admin);
        app(PublishWorkflow::class)->handle($workflow);

        return $type;
    }

    private function equipmentType(Workspace $workspace): RequestType
    {
        $type = $this->type($workspace, 'Equipment Access Request', 'Request equipment or internal access.');
        $this->field($type, 'equipment_type', 'Equipment type', 'select', true, [
            'options' => $this->options(['laptop', 'monitor', 'badge', 'system_access']),
        ]);
        $this->field($type, 'details', 'Details', 'textarea', true);
        $this->field($type, 'needed_by', 'Needed by', 'date', true);
        $this->field($type, 'business_reason', 'Business reason', 'textarea', true);
        $workflow = $this->workflow($workspace, $type, 'Equipment and access approval');
        $this->step($workflow, 'Operations Review', WorkspaceRole::Approver);
        app(PublishWorkflow::class)->handle($workflow);

        return $type;
    }

    private function createRequestHistory(Workspace $workspace, RequestType $purchase, RequestType $leave, RequestType $equipment): void
    {
        $requester = $this->users['requester'];
        $admin = $this->users['admin'];
        $approver = $this->users['approver'];

        $underBudget = $this->submit($workspace, $purchase, $requester, $this->purchasePayload('Office chair', 75, 'office'));
        $this->approvePending($underBudget, $admin);

        $midBudget = $this->submit($workspace, $purchase, $requester, $this->purchasePayload('Design software', 500, 'software'));
        $this->approvePending($midBudget, $admin);

        $large = $this->submit($workspace, $purchase, $requester, $this->purchasePayload('Storage array', 2500, 'other'));
        app(RejectRequestApproval::class)->handle($this->pending($large), $admin);

        $hardware = $this->submit($workspace, $purchase, $requester, $this->purchasePayload('Developer laptop', 750, 'hardware'));
        $this->approvePending($hardware, $admin);
        $this->approvePending($hardware, $approver);
        $this->approvePending($hardware, $approver);

        $leavePending = $this->submit($workspace, $leave, $requester, [
            'start_date' => now()->addDays(14)->toDateString(),
            'end_date' => now()->addDays(18)->toDateString(),
            'leave_type' => 'annual',
            'reason' => 'Family holiday.',
        ]);
        $equipmentCancelled = $this->submit($workspace, $equipment, $requester, [
            'equipment_type' => 'monitor',
            'details' => '27-inch monitor for the home office.',
            'needed_by' => now()->addDays(10)->toDateString(),
            'business_reason' => 'Improve remote-work productivity.',
        ]);
        app(CancelRequest::class)->handle($equipmentCancelled, $requester);
        $this->draft($workspace, $leave, $requester, [
            'start_date' => now()->addMonth()->toDateString(),
            'leave_type' => 'annual',
        ]);

        app(CreateRequestComment::class)->handle($midBudget, $requester, 'Budget owner confirmed the annual subscription.');
        app(CreateRequestComment::class)->handle($hardware, $approver, 'Technical specifications reviewed and approved.');
        app(CreateRequestComment::class)->handle($leavePending, $requester, 'Dates are flexible by one day if needed.');
    }

    private function type(Workspace $workspace, string $name, string $description): RequestType
    {
        return app(CreateRequestType::class)->handle($workspace, $this->users['owner'], [
            'name' => $name,
            'description' => $description,
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed>|null $config */
    private function field(RequestType $type, string $key, string $label, string $fieldType, bool $required = false, ?array $config = null): RequestTypeField
    {
        return app(CreateRequestTypeField::class)->handle($type, [
            'key' => $key,
            'label' => $label,
            'type' => $fieldType,
            'is_required' => $required,
            'config' => $config,
        ]);
    }

    private function workflow(Workspace $workspace, RequestType $type, string $name): Workflow
    {
        return app(CreateWorkflow::class)->handle($workspace, $type, $this->users['owner'], ['name' => $name]);
    }

    private function step(
        Workflow $workflow,
        string $name,
        WorkspaceRole $role,
        ?RequestTypeField $field = null,
        ?string $operator = null,
        mixed $value = null,
    ): void {
        app(SaveWorkflowStep::class)->create($workflow, [
            'name' => $name,
            'approver_type' => 'role',
            'approver_role' => $role->value,
            'condition_logic' => 'all',
            'conditions' => $field === null ? [] : [[
                'field_id' => $field->id,
                'operator' => $operator,
                'value' => $value,
            ]],
        ]);
    }

    /** @param list<string> $values @return list<array{value: string, label: string}> */
    private function options(array $values): array
    {
        return array_map(fn (string $value): array => [
            'value' => $value,
            'label' => str($value)->replace('_', ' ')->title()->toString(),
        ], $values);
    }

    /** @return array<string, mixed> */
    private function purchasePayload(string $item, int $cost, string $category): array
    {
        return [
            'item_name' => $item,
            'estimated_cost' => $cost,
            'category' => $category,
            'justification' => 'Required for current operational work.',
            'urgent' => false,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function draft(Workspace $workspace, RequestType $type, User $creator, array $payload): RequestSubmission
    {
        return app(CreateRequestSubmission::class)->handle($workspace, $type, $creator, $payload);
    }

    /** @param array<string, mixed> $payload */
    private function submit(Workspace $workspace, RequestType $type, User $creator, array $payload): RequestSubmission
    {
        return app(SubmitRequest::class)->handle($this->draft($workspace, $type, $creator, $payload));
    }

    private function approvePending(RequestSubmission $submission, User $actor): void
    {
        app(ApproveRequestApproval::class)->handle($this->pending($submission), $actor);
    }

    private function pending(RequestSubmission $submission): RequestApproval
    {
        return $submission->approvals()->where('status', RequestApprovalStatus::Pending)->firstOrFail();
    }
}
