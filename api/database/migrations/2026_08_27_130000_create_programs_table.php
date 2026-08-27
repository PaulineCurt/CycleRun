<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('goal_distance_m');
            $table->integer('goal_time_s')->nullable();
            $table->string('methodology');
            $table->jsonb('level_input');
            $table->jsonb('paces');
            $table->unsignedTinyInteger('duration_weeks');
            $table->unsignedTinyInteger('days_per_week');
            $table->date('start_date');
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
