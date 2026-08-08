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
        Schema::create('workflow_step_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_type_field_id')->constrained()->restrictOnDelete();
            $table->string('operator', 30);
            $table->json('value')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['workflow_step_id', 'position', 'id'], 'workflow_conditions_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_step_conditions');
    }
};
