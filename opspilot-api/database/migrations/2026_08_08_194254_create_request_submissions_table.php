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
        Schema::create('request_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20);
            $table->json('payload');
            $table->json('definition_snapshot')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at', 'id']);
            $table->index(['workspace_id', 'status', 'created_at', 'id']);
            $table->index(['created_by', 'status', 'created_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_submissions');
    }
};
