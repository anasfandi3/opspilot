<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RequestCatalogController;
use App\Http\Controllers\Api\V1\RequestSubmissionController;
use App\Http\Controllers\Api\V1\RequestTypeController;
use App\Http\Controllers\Api\V1\RequestTypeFieldController;
use App\Http\Controllers\Api\V1\WorkflowController;
use App\Http\Controllers\Api\V1\WorkflowStepController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\Api\V1\WorkspaceInvitationController;
use App\Http\Controllers\Api\V1\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:registration')->name('register');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [ProfileController::class, 'show'])->name('me.show');
        Route::patch('me', [ProfileController::class, 'update'])->name('me.update');
        Route::put('me/password', [ProfileController::class, 'updatePassword'])->name('me.password.update');

        Route::middleware('workspace.context')->group(function (): void {
            Route::post('invitations/{token}/accept', [WorkspaceInvitationController::class, 'accept'])->name('invitations.accept');
            Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
            Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
            Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');
            Route::patch('workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
            Route::post('workspaces/{workspace}/switch', [WorkspaceController::class, 'switchWorkspace'])->name('workspaces.switch');
            Route::post('workspaces/{workspace}/leave', [WorkspaceController::class, 'leave'])->name('workspaces.leave');
            Route::get('workspaces/{workspace}/members', [WorkspaceMemberController::class, 'index'])->name('workspaces.members.index');
            Route::delete('workspaces/{workspace}/members/{user}', [WorkspaceMemberController::class, 'destroy'])->name('workspaces.members.destroy');
            Route::patch('workspaces/{workspace}/members/{user}/role', [WorkspaceMemberController::class, 'updateRole'])->name('workspaces.members.role.update');
            Route::scopeBindings()->group(function (): void {
                Route::get('workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'index'])->name('workspaces.invitations.index');
                Route::post('workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store'])->name('workspaces.invitations.store');
                Route::delete('workspaces/{workspace}/invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy'])->name('workspaces.invitations.destroy');
                Route::post('workspaces/{workspace}/invitations/{invitation}/resend', [WorkspaceInvitationController::class, 'resend'])->name('workspaces.invitations.resend');
                Route::get('workspaces/{workspace}/request-types', [RequestTypeController::class, 'index'])->name('workspaces.request_types.index');
                Route::get('workspaces/{workspace}/request-catalog', [RequestCatalogController::class, 'index'])->name('workspaces.request_catalog.index');
                Route::post('workspaces/{workspace}/request-types', [RequestTypeController::class, 'store'])->name('workspaces.request_types.store');
                Route::get('workspaces/{workspace}/request-types/{requestType}', [RequestTypeController::class, 'show'])->name('workspaces.request_types.show');
                Route::patch('workspaces/{workspace}/request-types/{requestType}', [RequestTypeController::class, 'update'])->name('workspaces.request_types.update');
                Route::delete('workspaces/{workspace}/request-types/{requestType}', [RequestTypeController::class, 'destroy'])->name('workspaces.request_types.destroy');
                Route::post('workspaces/{workspace}/request-types/{requestType}/requests', [RequestSubmissionController::class, 'store'])->name('workspaces.request_types.requests.store');
                Route::get('workspaces/{workspace}/requests', [RequestSubmissionController::class, 'index'])->name('workspaces.requests.index');
                Route::get('workspaces/{workspace}/requests/{requestSubmission}', [RequestSubmissionController::class, 'show'])->name('workspaces.requests.show');
                Route::patch('workspaces/{workspace}/requests/{requestSubmission}', [RequestSubmissionController::class, 'update'])->name('workspaces.requests.update');
                Route::post('workspaces/{workspace}/requests/{requestSubmission}/submit', [RequestSubmissionController::class, 'submit'])->name('workspaces.requests.submit');
                Route::post('workspaces/{workspace}/requests/{requestSubmission}/cancel', [RequestSubmissionController::class, 'cancel'])->name('workspaces.requests.cancel');
                Route::post('workspaces/{workspace}/request-types/{requestType}/fields', [RequestTypeFieldController::class, 'store'])->name('workspaces.request_types.fields.store');
                Route::post('workspaces/{workspace}/request-types/{requestType}/fields/reorder', [RequestTypeFieldController::class, 'reorder'])->name('workspaces.request_types.fields.reorder');
                Route::patch('workspaces/{workspace}/request-types/{requestType}/fields/{field}', [RequestTypeFieldController::class, 'update'])->name('workspaces.request_types.fields.update');
                Route::delete('workspaces/{workspace}/request-types/{requestType}/fields/{field}', [RequestTypeFieldController::class, 'destroy'])->name('workspaces.request_types.fields.destroy');
                Route::get('workspaces/{workspace}/request-types/{requestType}/workflows', [WorkflowController::class, 'index'])->name('workspaces.request_types.workflows.index');
                Route::post('workspaces/{workspace}/request-types/{requestType}/workflows', [WorkflowController::class, 'store'])->name('workspaces.request_types.workflows.store');
                Route::get('workspaces/{workspace}/request-types/{requestType}/workflows/{workflow}', [WorkflowController::class, 'show'])->name('workspaces.request_types.workflows.show');
                Route::patch('workspaces/{workspace}/request-types/{requestType}/workflows/{workflow}', [WorkflowController::class, 'update'])->name('workspaces.request_types.workflows.update');
                Route::delete('workspaces/{workspace}/request-types/{requestType}/workflows/{workflow}', [WorkflowController::class, 'destroy'])->name('workspaces.request_types.workflows.destroy');
                Route::post('workspaces/{workspace}/request-types/{requestType}/workflows/{workflow}/publish', [WorkflowController::class, 'publish'])->name('workspaces.request_types.workflows.publish');
                Route::post('workspaces/{workspace}/request-types/{requestType}/workflows/{workflow}/clone', [WorkflowController::class, 'clone'])->name('workspaces.request_types.workflows.clone');
                Route::post('workspaces/{workspace}/request-types/{requestType}/workflows/{workflow}/steps', [WorkflowStepController::class, 'store'])->name('workspaces.request_types.workflows.steps.store');
                Route::post('workspaces/{workspace}/request-types/{requestType}/workflows/{workflow}/steps/reorder', [WorkflowStepController::class, 'reorder'])->name('workspaces.request_types.workflows.steps.reorder');
                Route::patch('workspaces/{workspace}/request-types/{requestType}/workflows/{workflow}/steps/{step}', [WorkflowStepController::class, 'update'])->name('workspaces.request_types.workflows.steps.update');
                Route::delete('workspaces/{workspace}/request-types/{requestType}/workflows/{workflow}/steps/{step}', [WorkflowStepController::class, 'destroy'])->name('workspaces.request_types.workflows.steps.destroy');
            });
        });
    });
});
