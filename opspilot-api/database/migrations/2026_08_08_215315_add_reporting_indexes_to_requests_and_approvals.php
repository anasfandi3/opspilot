<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_submissions', function (Blueprint $table) {
            $table->index(['workspace_id', 'submitted_at'], 'requests_workspace_submitted_index');
            $table->index(['workspace_id', 'status', 'resolved_at'], 'requests_workspace_status_resolved_index');
            $table->index(['workspace_id', 'cancelled_at'], 'requests_workspace_cancelled_index');
        });

        Schema::table('request_approvals', function (Blueprint $table) {
            $table->index(['workspace_id', 'status', 'decided_at'], 'approvals_workspace_status_decided_index');
        });
    }

    public function down(): void
    {
        Schema::table('request_submissions', function (Blueprint $table) {
            $table->dropIndex('requests_workspace_submitted_index');
            $table->dropIndex('requests_workspace_status_resolved_index');
            $table->dropIndex('requests_workspace_cancelled_index');
        });

        Schema::table('request_approvals', function (Blueprint $table) {
            $table->dropIndex('approvals_workspace_status_decided_index');
        });
    }
};
