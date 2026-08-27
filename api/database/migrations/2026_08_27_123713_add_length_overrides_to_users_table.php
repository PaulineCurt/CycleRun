<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Manual overrides for predictions: unlike typical_cycle_length/typical_menses_length
            // (the pre-measurement declared fallback, locked once real data exists), these take
            // priority over the computed average even after measurement — an explicit "force this
            // number" escape hatch, set only by the user, never derived.
            $table->unsignedSmallInteger('cycle_length_override')->nullable()->after('typical_menses_length');
            $table->unsignedSmallInteger('menses_length_override')->nullable()->after('cycle_length_override');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cycle_length_override', 'menses_length_override']);
        });
    }
};
