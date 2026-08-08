<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->string('status', 20);
            $table->unsignedTinyInteger('pending_guard')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['request_submission_id', 'workflow_step_id']);
            $table->unique(['request_submission_id', 'pending_guard']);
            $table->index(['workspace_id', 'status', 'activated_at', 'id']);
            $table->index(['request_submission_id', 'position', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_approvals');
    }
};
