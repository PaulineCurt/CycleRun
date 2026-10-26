<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            // Declared runner level (§4.1 moteurB_three_levels.md). Editable after
            // the fact. Nullable so no backfill is needed; every new program sets it.
            $table->string('level')->nullable()->after('methodology');
            // How well anchored the VMA behind the plan is: default | declared |
            // measured (§6 refinement ladder).
            $table->string('vma_source')->nullable()->after('level');
        });

        // A débutante can finish stage 1 with no pace anchor at all.
        Schema::table('programs', function (Blueprint $table) {
            $table->jsonb('level_input')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['level', 'vma_source']);
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->jsonb('level_input')->nullable(false)->change();
        });
    }
};
