<?php

namespace App\Support;

use App\Enums\RequestApprovalStatus;
use App\Enums\RequestStatus;
use App\Models\RequestApproval;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceDashboardService
{
    /**
     * @return array{
     *   requests: array{total: int, draft: int, submitted: int, approved: int, rejected: int, cancelled: int},
     *   approvals: array{pending: int},
     *   request_types: array{active: int},
     *   members: array{total: int},
     *   recent_requests: Collection<int, RequestSubmission>
     * }
     */
    public function get(Workspace $workspace): array
    {
        $grouped = RequestSubmission::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $counts = [];
        foreach (RequestStatus::cases() as $status) {
            $counts[$status->value] = (int) ($grouped[$status->value] ?? 0);
        }

        return [
            'requests' => ['total' => array_sum($counts), ...$counts],
            'approvals' => [
                'pending' => RequestApproval::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('status', RequestApprovalStatus::Pending)
                    ->count(),
            ],
            'request_types' => [
                'active' => RequestType::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('is_active', true)
                    ->count(),
            ],
            'members' => [
                'total' => WorkspaceMembership::query()->where('workspace_id', $workspace->id)->count(),
            ],
            'recent_requests' => RequestSubmission::query()
                ->where('workspace_id', $workspace->id)
                ->with(['requestType:id,name,slug', 'creator:id,name'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
        ];
    }
}
