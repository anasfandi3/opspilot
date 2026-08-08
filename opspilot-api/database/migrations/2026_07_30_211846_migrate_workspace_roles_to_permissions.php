<?php

use App\Actions\SynchronizeWorkspacePermissions;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspacePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $synchronize = app(SynchronizeWorkspacePermissions::class);
        $permissions = app(WorkspacePermissions::class);

        Workspace::query()->eachById(function (Workspace $workspace) use ($synchronize, $permissions): void {
            $synchronize->handle($workspace);

            DB::table('workspace_user')->where('workspace_id', $workspace->id)
                ->orderBy('id')
                ->each(function (object $membership) use ($workspace, $permissions): void {
                    $role = WorkspaceRole::fromLegacy($membership->role);

                    $permissions->assign(User::query()->findOrFail($membership->user_id), $workspace, $role);
                });
        });

        Schema::table('workspace_user', fn (Blueprint $table) => $table->dropIndex(['role']));
        Schema::table('workspace_user', fn (Blueprint $table) => $table->dropColumn('role'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspace_user', function (Blueprint $table): void {
            $table->string('role', 20)->nullable()->index();
        });

        DB::table('workspace_user')->orderBy('id')->each(function (object $membership): void {
            $role = DB::table(config('permission.table_names.roles'))
                ->join(config('permission.table_names.model_has_roles'), 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.workspace_id', $membership->workspace_id)
                ->where('model_has_roles.model_id', $membership->user_id)
                ->where('model_has_roles.model_type', User::class)
                ->value('roles.name');

            DB::table('workspace_user')->where('id', $membership->id)->update([
                'role' => $role === WorkspaceRole::Owner->value ? 'owner' : ($role === WorkspaceRole::Admin->value ? 'admin' : 'member'),
            ]);
        });
    }
};
