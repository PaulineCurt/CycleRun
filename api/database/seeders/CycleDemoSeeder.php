<?php

namespace Database\Seeders;

use App\Enums\Flow;
use App\Enums\Level;
use App\Enums\Methodology;
use App\Enums\ProgramStatus;
use App\Enums\SessionStatus;
use App\Models\Cycle;
use App\Models\DayLog;
use App\Models\User;
use App\Services\Training\BuildParams;
use App\Services\Training\ProgramBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo account with a realistic cycle history, for manually exercising the calendar,
 * home countdown, and cycle-analysis screens with real variation instead of empty state.
 *
 * Everything is anchored on today, so re-running it always fills the current month:
 * cycles, daily feelings shaped by the cycle phase, and an active 5K plan with the
 * past sessions already done.
 *
 * Run with: php artisan db:seed --class=Database\\Seeders\\CycleDemoSeeder
 */
class CycleDemoSeeder extends Seeder
{
    /** How far back daily feelings are logged — covers the whole previous cycle. */
    private const DAY_LOG_DAYS = 35;

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
        $user->dayLogs()->delete();
        $user->programs()->delete(); // sessions cascade

        [$starts, $mensesLengths] = $this->seedCycles($user);
        $this->seedDayLogs($user, $starts, $mensesLengths);
        $this->seedProgram($user);

        $this->command?->info(sprintf(
            'Demo user ready: demo@cyclerun.dev / password (%d cycles, %d day logs, %d sessions)',
            $user->cycles()->count(),
            $user->dayLogs()->count(),
            $user->programs()->first()?->sessions()->count() ?? 0,
        ));
    }

    /**
     * @return array{0: list<CarbonImmutable>, 1: list<int>} cycle J1s and menses
     *                                                       lengths, oldest -> newest
     */
    private function seedCycles(User $user): array
    {
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

        return [$starts, $mensesLengths];
    }

    /**
     * Daily feelings over the last weeks, shaped by where each day falls in its cycle.
     * Deterministic (no randomness) so screenshots are reproducible.
     *
     * @param  list<CarbonImmutable>  $starts
     * @param  list<int>  $mensesLengths
     */
    private function seedDayLogs(User $user, array $starts, array $mensesLengths): void
    {
        $today = CarbonImmutable::today();

        for ($offset = self::DAY_LOG_DAYS; $offset >= 0; $offset--) {
            $date = $today->subDays($offset);

            [$index, $cycleDay, $cycleLength] = $this->cyclePosition($date, $starts);
            if ($index === null) {
                continue;
            }
            $mensesLength = $mensesLengths[$index];

            // A real user forgets some days — but never during her period.
            if ($cycleDay > $mensesLength && $offset % 4 === 3) {
                continue;
            }

            DayLog::create(['user_id' => $user->id, 'date' => $date->toDateString()]
                + $this->feelingFor($cycleDay, $cycleLength, $mensesLength));
        }
    }

    /**
     * @param  list<CarbonImmutable>  $starts
     * @return array{0: int|null, 1: int|null, 2: int|null} [cycle index, cycle day (1-based), cycle length]
     */
    private function cyclePosition(CarbonImmutable $date, array $starts): array
    {
        for ($i = count($starts) - 1; $i >= 0; $i--) {
            if ($starts[$i]->lessThanOrEqualTo($date)) {
                $length = isset($starts[$i + 1]) ? (int) $starts[$i]->diffInDays($starts[$i + 1]) : 28;

                return [$i, (int) $starts[$i]->diffInDays($date) + 1, $length];
            }
        }

        return [null, null, null];
    }

    /**
     * @return array{flow: ?Flow, pain: ?int, energy: int, symptoms: array<string, int>, note: ?string}
     */
    private function feelingFor(int $cycleDay, int $cycleLength, int $mensesLength): array
    {
        $menses = [
            1 => [Flow::Medium, 2, 2, ['cramps' => 2, 'back_pain' => 1, 'low_mood' => 1], 'Grosse fatigue, footing tout doux.'],
            2 => [Flow::Heavy, 3, 2, ['cramps' => 3, 'back_pain' => 2, 'headache' => 1], null],
            3 => [Flow::Medium, 2, 3, ['cramps' => 1, 'bloating' => 1], null],
            4 => [Flow::Light, 1, 3, ['bloating' => 1], null],
            5 => [Flow::Spotting, 0, 4, [], 'Ça revient, petit footing ok.'],
        ];

        // Flow only within the cycle's declared menses, so logs and cycles agree.
        if ($cycleDay <= $mensesLength && isset($menses[$cycleDay])) {
            [$flow, $pain, $energy, $symptoms, $note] = $menses[$cycleDay];

            return compact('flow', 'pain', 'energy', 'symptoms', 'note');
        }

        $daysLeft = $cycleLength - $cycleDay;

        return match (true) {
            // Follicular: energy climbs back.
            $cycleDay <= 13 => [
                'flow' => null, 'pain' => 0, 'energy' => $cycleDay % 3 === 0 ? 4 : 5,
                'symptoms' => $cycleDay % 4 === 0 ? ['muscle_soreness' => 1] : [],
                'note' => $cycleDay === 9 ? 'Jambes légères, super sortie !' : null,
            ],
            // Ovulation window.
            $cycleDay <= 16 => [
                'flow' => null, 'pain' => $cycleDay === 14 ? 1 : 0, 'energy' => 5,
                'symptoms' => $cycleDay === 14 ? ['breast_tenderness' => 1] : [],
                'note' => null,
            ],
            // Late luteal: PMS builds up.
            $daysLeft <= 4 => [
                'flow' => null, 'pain' => 1, 'energy' => 2,
                'symptoms' => ['bloating' => 2, 'breast_tenderness' => 2, 'irritability' => 1, 'water_retention' => 1],
                'note' => $daysLeft === 2 ? 'Gonflée et irritable, sortie raccourcie.' : null,
            ],
            // Early luteal.
            default => [
                'flow' => null, 'pain' => 0, 'energy' => $daysLeft <= 8 ? 3 : 4,
                'symptoms' => $daysLeft <= 8 ? ['bloating' => 1] : [],
                'note' => null,
            ],
        };
    }

    /**
     * An active 5K plan that started three weeks ago, built by the real engine (same
     * path as POST /programs), with the past sessions logged as done — one skipped.
     */
    private function seedProgram(User $user): void
    {
        $today = CarbonImmutable::today();

        $params = new BuildParams(
            levelInput: ['kind' => 'field_test', 'vma_kmh' => 12.5],
            level: Level::Intermediaire,
            goalDistanceM: 5000,
            goalTimeS: 28 * 60,
            durationWeeks: 10,
            daysPerWeek: 3,
            healthFlags: [],
            startDate: $today->subWeeks(3)->startOfWeek(),
        );

        $result = app(ProgramBuilder::class)->build($params);
        $feasibility = $result->feasibility;

        $program = $user->programs()->create([
            'goal_distance_m' => $params->goalDistanceM,
            'goal_time_s' => $params->goalTimeS,
            'methodology' => Methodology::Vma,
            'level' => $params->level,
            'vma_source' => $result->level->vmaProvenance,
            'level_input' => $params->levelInput,
            'paces' => $result->level->paces,
            'duration_weeks' => $params->durationWeeks,
            'days_per_week' => $params->daysPerWeek,
            'start_date' => $params->startDate->toDateString(),
            'status' => ProgramStatus::Active,
            'feasibility' => [
                'feasible' => $feasibility->feasible,
                'projected_time_s' => $feasibility->projectedTimeS,
                'achievable_time_s' => $feasibility->achievableTimeS,
                'suggested_goal_time_s' => $feasibility->suggestedGoalTimeS,
                'suggested_duration_weeks' => $feasibility->suggestedDurationWeeks,
                'alert' => $feasibility->alert,
            ],
            'warnings' => $result->warnings,
        ]);

        $pastIndex = 0;
        foreach ($result->sessions as $draft) {
            $isPast = $draft->plannedDate->lessThan($today);
            $durationS = (int) ($draft->plannedLoad['duration_s'] ?? 1800);

            // The 4th past session was skipped — everything else before today was run.
            $status = match (true) {
                ! $isPast => SessionStatus::Planned,
                $pastIndex === 3 => SessionStatus::Skipped,
                default => SessionStatus::Done,
            };

            $program->sessions()->create([
                'planned_date' => $draft->plannedDate->toDateString(),
                'week_index' => $draft->weekIndex,
                'type' => $draft->type,
                'planned_load' => $draft->plannedLoad,
                'target_pace_min_s' => $draft->targetPaceMinS,
                'target_pace_max_s' => $draft->targetPaceMaxS,
                'base_generated' => $draft->toArray(),
                'status' => $status,
                'actual' => $status === SessionStatus::Done ? [
                    'rpe' => [4, 5, 6, 7][$pastIndex % 4],
                    'duration_s' => $durationS + ($pastIndex % 3 - 1) * 60,
                    // ~6:30/km on an easy-ish 5K-plan session.
                    'distance_m' => (int) round($durationS / 390 * 1000),
                ] : null,
            ]);

            if ($isPast) {
                $pastIndex++;
            }
        }
    }
}
