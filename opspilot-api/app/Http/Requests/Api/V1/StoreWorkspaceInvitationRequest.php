<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createInvitation', $this->route('workspace'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(WorkspaceRole::class)->only([
                WorkspaceRole::Admin, WorkspaceRole::Approver, WorkspaceRole::Requester, WorkspaceRole::Auditor,
            ])],
        ];
    }
}
