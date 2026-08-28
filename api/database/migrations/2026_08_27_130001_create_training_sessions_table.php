<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->date('planned_date');
            $table->unsignedTinyInteger('week_index');
            $table->string('type');
            $table->jsonb('planned_load');
            $table->unsignedSmallInteger('target_pace_min_s')->nullable();
            $table->unsignedSmallInteger('target_pace_max_s')->nullable();
            $table->jsonb('base_generated');
            $table->jsonb('adaptation')->nullable();
            $table->string('status')->default('planned');
            $table->jsonb('actual')->nullable();
            $table->timestamps();

            $table->index(['program_id', 'planned_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
