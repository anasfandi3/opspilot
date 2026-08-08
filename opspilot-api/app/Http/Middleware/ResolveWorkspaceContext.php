<?php

namespace App\Http\Middleware;

use App\Support\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspaceContext
{
    public function __construct(private WorkspaceContext $workspaceContext) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $previousTeamId = getPermissionsTeamId();
        $user = $request->user();
        $workspace = $user?->current_workspace_id
            ? $user->workspaces()->whereKey($user->current_workspace_id)->first()
            : null;

        $this->workspaceContext->set($workspace);
        setPermissionsTeamId($workspace?->id);
        Context::add('workspace_id', $workspace?->id);

        try {
            return $next($request);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}
