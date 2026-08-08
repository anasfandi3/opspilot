<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
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
            });
        });
    });
});
