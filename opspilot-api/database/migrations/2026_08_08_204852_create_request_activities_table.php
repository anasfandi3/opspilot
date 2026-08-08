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
        Schema::create('request_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type', 40);
            $table->foreignId('request_comment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('request_attachment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('request_approval_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['request_submission_id', 'created_at', 'id']);
            $table->index(['workspace_id', 'created_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_activities');
    }
};
