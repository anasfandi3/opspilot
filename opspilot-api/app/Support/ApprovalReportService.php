<?php

namespace App\Support;

use App\Enums\RequestApprovalStatus;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ApprovalReportService
{
    public function __construct(private ReportDurationExpression $durations) {}

    /** @param array{request_type_id?: int} $filters @return array<string, mixed> */
    public function get(Workspace $workspace, ReportDateRange $range, array $filters): array
    {
        $current = $this->approvals($workspace, $filters)
            ->where('request_approvals.status', RequestApprovalStatus::Pending->value)
            ->selectRaw('COUNT(*) as aggregate_count, MIN(request_approvals.activated_at) as oldest_activated_at')
            ->first();
        $decisions = $this->approvals($workspace, $filters)
            ->whereIn('request_approvals.status', [
                RequestApprovalStatus::Approved->value,
                RequestApprovalStatus::Rejected->value,
            ])
            ->where('request_approvals.decided_at', '>=', $range->start)
            ->where('request_approvals.decided_at', '<', $range->endExclusive);
        $hours = $this->durations->hours('request_approvals.activated_at', 'request_approvals.decided_at');
        $totals = (clone $decisions)->selectRaw(
            "COUNT(*) as aggregate_count,
            SUM(CASE WHEN request_approvals.status = ? THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN request_approvals.status = ? THEN 1 ELSE 0 END) as rejected_count,
            AVG({$hours}) as average_hours,
            AVG(CASE WHEN request_approvals.status = ? THEN {$hours} END) as approved_average_hours,
            AVG(CASE WHEN request_approvals.status = ? THEN {$hours} END) as rejected_average_hours",
            [
                RequestApprovalStatus::Approved->value,
                RequestApprovalStatus::Rejected->value,
                RequestApprovalStatus::Approved->value,
                RequestApprovalStatus::Rejected->value,
            ],
        )->first();

        $trendRows = (clone $decisions)
            ->selectRaw(
                'DATE(request_approvals.decided_at) as report_date,
                SUM(CASE WHEN request_approvals.status = ? THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN request_approvals.status = ? THEN 1 ELSE 0 END) as rejected_count',
                [RequestApprovalStatus::Approved->value, RequestApprovalStatus::Rejected->value],
            )
            ->groupBy('report_date')
            ->get()
            ->keyBy('report_date');

        $byStep = (clone $decisions)
            ->join('workflow_steps', 'workflow_steps.id', '=', 'request_approvals.workflow_step_id')
            ->selectRaw(
                'workflow_steps.id, workflow_steps.name,
                SUM(CASE WHEN request_approvals.status = ? THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN request_approvals.status = ? THEN 1 ELSE 0 END) as rejected_count,
                COUNT(*) as aggregate_count',
                [RequestApprovalStatus::Approved->value, RequestApprovalStatus::Rejected->value],
            )
            ->groupBy('workflow_steps.id', 'workflow_steps.name')
            ->orderByDesc('aggregate_count')
            ->orderBy('workflow_steps.id')
            ->get()
            ->map(fn (object $row): array => [
                'workflow_step' => ['id' => (int) $row->id, 'name' => $row->name],
                'approved' => (int) $row->approved_count,
                'rejected' => (int) $row->rejected_count,
                'total' => (int) $row->aggregate_count,
            ])->all();

        return [
            'period' => $range->period(),
            'current' => [
                'pending' => (int) $current->aggregate_count,
                'oldest_pending_activated_at' => $current->oldest_activated_at === null
                    ? null
                    : CarbonImmutable::parse($current->oldest_activated_at, 'UTC')->toISOString(),
            ],
            'decisions' => [
                'total' => (int) $totals->aggregate_count,
                'approved' => (int) $totals->approved_count,
                'rejected' => (int) $totals->rejected_count,
                'average_decision_hours' => $this->round($totals->average_hours),
                'approved_average_hours' => $this->round($totals->approved_average_hours),
                'rejected_average_hours' => $this->round($totals->rejected_average_hours),
                'trend' => array_map(function (string $date) use ($trendRows): array {
                    $row = $trendRows->get($date);
                    $approved = (int) ($row->approved_count ?? 0);
                    $rejected = (int) ($row->rejected_count ?? 0);

                    return ['date' => $date, 'approved' => $approved, 'rejected' => $rejected, 'total' => $approved + $rejected];
                }, $range->dates()),
                'by_step' => $byStep,
            ],
        ];
    }

    /** @param array{request_type_id?: int} $filters */
    private function approvals(Workspace $workspace, array $filters): Builder
    {
        return DB::table('request_approvals')
            ->join('request_submissions', function ($join) use ($workspace): void {
                $join->on('request_submissions.id', '=', 'request_approvals.request_submission_id')
                    ->where('request_submissions.workspace_id', '=', $workspace->id);
            })
            ->where('request_approvals.workspace_id', $workspace->id)
            ->when(
                $filters['request_type_id'] ?? null,
                fn (Builder $query, int $requestTypeId): Builder => $query->where('request_submissions.request_type_id', $requestTypeId),
            );
    }

    private function round(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }
}
