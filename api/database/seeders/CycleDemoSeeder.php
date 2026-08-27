<?php

namespace Database\Seeders;

use App\Models\Cycle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo account with a realistic cycle history, for manually exercising the calendar,
 * home countdown, and cycle-analysis screens with real variation instead of empty state.
 *
 * Run with: php artisan db:seed --class=Database\\Seeders\\CycleDemoSeeder
 */
class CycleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@cyclerun.dev'],
            [
                'name' => 'Demo CycleRun',
                'password' => Hash::make('password'),
                'birth_date' => '1996-06-15',
            ],
        );

        $user->cycles()->delete();

        // Gaps between consecutive J1s, oldest to newest — mild jitter (27-30j) so the
        // analysis screen shows real variation instead of a flat line.
        $cycleGaps = [28, 27, 30, 28, 29, 27, 28, 30];
        // One menses duration per cycle (9 cycles total), also jittered (4-6j).
        $mensesLengths = [5, 4, 6, 5, 4, 5, 6, 4, 5];

        $mostRecentStart = CarbonImmutable::today()->subDays(5);

        $starts = [$mostRecentStart];
        foreach ($cycleGaps as $gap) {
            $starts[] = $starts[array_key_last($starts)]->subDays($gap);
        }
        $starts = array_reverse($starts); // oldest -> newest

        foreach ($starts as $index => $startedOn) {
            $isMostRecent = $index === count($starts) - 1;

            Cycle::create([
                'user_id' => $user->id,
                'started_on' => $startedOn->toDateString(),
                // The current cycle is left "ongoing" (no declared end yet), like a real
                // user who hasn't logged the end of her period.
                'menses_ended_on' => $isMostRecent
                    ? null
                    : $startedOn->addDays($mensesLengths[$index] - 1)->toDateString(),
            ]);
        }

        $this->command?->info("Demo user ready: demo@cyclerun.dev / password ({$user->cycles()->count()} cycles)");
    }
}
