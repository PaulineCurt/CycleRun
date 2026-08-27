<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            // Decided once at generation, shown every time the plan is opened:
            // the feasibility check result and the overload / safety warnings.
            $table->jsonb('feasibility')->nullable();
            $table->jsonb('warnings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['feasibility', 'warnings']);
        });
    }
};
