<?php

namespace App\Support;

use App\Enums\RequestStatus;
use App\Models\Workspace;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class RequestReportService
{
    public function __construct(private ReportDurationExpression $durations) {}

    /** @param array{request_type_id?: int} $filters @return array<string, mixed> */
    public function get(Workspace $workspace, ReportDateRange $range, array $filters): array
    {
        $created = $this->submissions($workspace, $filters)
            ->where('request_submissions.created_at', '>=', $range->start)
            ->where('request_submissions.created_at', '<', $range->endExclusive);
        $statusRows = (clone $created)
            ->selectRaw('request_submissions.status, COUNT(*) as aggregate')
            ->groupBy('request_submissions.status')
            ->pluck('aggregate', 'status');
        $statusCounts = [];
        foreach (RequestStatus::cases() as $status) {
            $statusCounts[$status->value] = (int) ($statusRows[$status->value] ?? 0);
        }

        $byRequestType = (clone $created)
            ->join('request_types', function ($join) use ($workspace): void {
                $join->on('request_types.id', '=', 'request_submissions.request_type_id')
                    ->where('request_types.workspace_id', '=', $workspace->id);
            })
            ->selectRaw('request_types.id, request_types.name, request_types.slug, COUNT(*) as aggregate')
            ->groupBy('request_types.id', 'request_types.name', 'request_types.slug')
            ->orderByDesc('aggregate')
            ->orderBy('request_types.id')
            ->get()
            ->map(fn (object $row): array => [
                'request_type' => [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'slug' => $row->slug,
                ],
                'count' => (int) $row->aggregate,
            ])->all();

        $trendCounts = (clone $created)
            ->selectRaw('DATE(request_submissions.created_at) as report_date, COUNT(*) as aggregate')
            ->groupBy('report_date')
            ->pluck('aggregate', 'report_date');

        return [
            'period' => $range->period(),
            'created' => [
                'total' => array_sum($statusCounts),
                'current_status' => $statusCounts,
                'by_request_type' => $byRequestType,
                'trend' => array_map(fn (string $date): array => [
                    'date' => $date,
                    'count' => (int) ($trendCounts[$date] ?? 0),
                ], $range->dates()),
            ],
            'lifecycle' => [
                'submitted' => $this->timestampCount($workspace, $range, $filters, 'submitted_at'),
                'approved' => $this->timestampCount($workspace, $range, $filters, 'resolved_at', RequestStatus::Approved),
                'rejected' => $this->timestampCount($workspace, $range, $filters, 'resolved_at', RequestStatus::Rejected),
                'cancelled' => $this->timestampCount($workspace, $range, $filters, 'cancelled_at'),
                'resolution' => $this->resolution($workspace, $range, $filters),
            ],
        ];
    }

    /** @param array{request_type_id?: int} $filters */
    private function submissions(Workspace $workspace, array $filters): Builder
    {
        return DB::table('request_submissions')
            ->where('request_submissions.workspace_id', $workspace->id)
            ->when(
                $filters['request_type_id'] ?? null,
                fn (Builder $query, int $requestTypeId): Builder => $query->where('request_submissions.request_type_id', $requestTypeId),
            );
    }

    /** @param array{request_type_id?: int} $filters */
    private function timestampCount(
        Workspace $workspace,
        ReportDateRange $range,
        array $filters,
        string $column,
        ?RequestStatus $status = null,
    ): int {
        return $this->submissions($workspace, $filters)
            ->when($status, fn (Builder $query, RequestStatus $status): Builder => $query->where('status', $status->value))
            ->where("request_submissions.{$column}", '>=', $range->start)
            ->where("request_submissions.{$column}", '<', $range->endExclusive)
            ->count();
    }

    /** @param array{request_type_id?: int} $filters @return array{count: int, average_hours: ?float, approved_average_hours: ?float, rejected_average_hours: ?float} */
    private function resolution(Workspace $workspace, ReportDateRange $range, array $filters): array
    {
        $hours = $this->durations->hours('request_submissions.submitted_at', 'request_submissions.resolved_at');
        $row = $this->submissions($workspace, $filters)
            ->whereIn('request_submissions.status', [RequestStatus::Approved->value, RequestStatus::Rejected->value])
            ->whereNotNull('request_submissions.submitted_at')
            ->whereNotNull('request_submissions.resolved_at')
            ->where('request_submissions.resolved_at', '>=', $range->start)
            ->where('request_submissions.resolved_at', '<', $range->endExclusive)
            ->selectRaw(
                "COUNT(*) as aggregate_count,
                AVG({$hours}) as average_hours,
                AVG(CASE WHEN request_submissions.status = ? THEN {$hours} END) as approved_average_hours,
                AVG(CASE WHEN request_submissions.status = ? THEN {$hours} END) as rejected_average_hours",
                [RequestStatus::Approved->value, RequestStatus::Rejected->value],
            )->first();

        return [
            'count' => (int) $row->aggregate_count,
            'average_hours' => $this->round($row->average_hours),
            'approved_average_hours' => $this->round($row->approved_average_hours),
            'rejected_average_hours' => $this->round($row->rejected_average_hours),
        ];
    }

    private function round(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }
}
