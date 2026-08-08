<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportRequest;
use App\Http\Resources\Api\V1\DashboardRequestResource;
use App\Models\Workspace;
use App\Support\ApprovalReportService;
use App\Support\ReportDateRange;
use App\Support\RequestReportService;
use App\Support\WorkspaceDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportingController extends Controller
{
    public function dashboard(
        Request $request,
        Workspace $workspace,
        WorkspaceDashboardService $dashboard,
    ): JsonResponse {
        Gate::authorize('viewReports', $workspace);
        $data = $dashboard->get($workspace);
        $data['recent_requests'] = DashboardRequestResource::collection($data['recent_requests'])->resolve($request);

        return response()->json(['data' => $data]);
    }

    public function requests(
        ReportRequest $request,
        Workspace $workspace,
        RequestReportService $reports,
    ): JsonResponse {
        $filters = $request->validated();

        return response()->json([
            'data' => $reports->get($workspace, ReportDateRange::fromFilters($filters), $filters),
        ]);
    }

    public function approvals(
        ReportRequest $request,
        Workspace $workspace,
        ApprovalReportService $reports,
    ): JsonResponse {
        $filters = $request->validated();

        return response()->json([
            'data' => $reports->get($workspace, ReportDateRange::fromFilters($filters), $filters),
        ]);
    }
}
