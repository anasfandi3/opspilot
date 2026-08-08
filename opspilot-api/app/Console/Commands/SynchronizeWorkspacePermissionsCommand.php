<?php

namespace App\Console\Commands;

use App\Actions\SynchronizeWorkspacePermissions;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync')]
#[Description('Synchronize workspace-scoped roles and permissions')]
class SynchronizeWorkspacePermissionsCommand extends Command
{
    public function handle(SynchronizeWorkspacePermissions $synchronize): int
    {
        Workspace::query()->eachById(static fn (Workspace $workspace) => $synchronize->handle($workspace));
        $this->components->info('Workspace permissions synchronized.');

        return self::SUCCESS;
    }
}
