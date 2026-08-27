<?php

namespace App\Http\Controllers;

use App\Services\Cycle\CycleCalculator;
use App\Services\Cycle\PhaseResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class CycleAnalysisController extends Controller
{
    private const FORECAST_COUNT = 3;

    public function __invoke(Request $request, CycleCalculator $calculator, PhaseResolver $resolver)
    {
        $user = $request->user();
        $cycles = $user->cycles()->orderBy('started_on')->get();
        $exclusionPeriods = $user->exclusionPeriods;

        $mensesLength = $calculator->mensesLength($cycles, $user->typical_menses_length);
        $mensesVariation = $calculator->mensesLengthVariation($cycles);

        $cycleLength = $calculator->cycleLength($cycles, $user->typical_cycle_length, $exclusionPeriods);
        $birthDate = $user->birth_date?->toImmutable();
        $regularity = $calculator->regularity($cycles, $birthDate, CarbonImmutable::today(), $exclusionPeriods);

        $sortedCycles = $cycles->sortBy('started_on')->values();

        $lastCompletedMenses = $sortedCycles
            ->filter(fn ($cycle) => $cycle->menses_ended_on !== null)
            ->last();
        $lastMensesDuration = $lastCompletedMenses
            ? $lastCompletedMenses->started_on->diffInDays($lastCompletedMenses->menses_ended_on) + 1
            : null;

        $lastCycleDuration = null;
        if ($sortedCycles->count() >= 2) {
            $last = $sortedCycles->last();
            $previous = $sortedCycles->get($sortedCycles->count() - 2);
            $lastCycleDuration = $previous->started_on->diffInDays($last->started_on);
        }

        $history = $sortedCycles
            ->values()
            ->map(function ($cycle, int $index) use ($sortedCycles) {
                $next = $sortedCycles->get($index + 1);

                return [
                    'started_on' => $cycle->started_on->toDateString(),
                    'menses_ended_on' => $cycle->menses_ended_on?->toDateString(),
                    'menses_duration' => $cycle->menses_ended_on
                        ? $cycle->started_on->diffInDays($cycle->menses_ended_on) + 1
                        : null,
                    'cycle_duration' => $next ? $cycle->started_on->diffInDays($next->started_on) : null,
                ];
            })
            ->reverse()
            ->values();

        $forecast = [];
        if ($sortedCycles->isNotEmpty()) {
            $currentCycle = $sortedCycles->last();
            $phase = $resolver->resolve(
                date: CarbonImmutable::today(),
                cycleStart: $currentCycle->started_on->toImmutable(),
                cycleLength: $cycleLength,
                mensesLength: $mensesLength,
                regularity: $regularity,
            );

            $cycleLengthDays = max(1, (int) round($cycleLength->days));
            $mensesLengthDays = max(1, (int) round($mensesLength->days));

            for ($i = 0; $i < self::FORECAST_COUNT; $i++) {
                $start = $phase->predictedNextStart->addDays($i * $cycleLengthDays);
                $end = $start->addDays($mensesLengthDays - 1);

                $forecast[] = [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                ];
            }
        }

        return response()->json([
            'menses' => [
                'last_duration' => $lastMensesDuration,
                'variation_days' => $mensesVariation,
                'sample_size' => $mensesLength->sampleSize,
            ],
            'cycle' => [
                'last_duration' => $lastCycleDuration,
                'variation_days' => $regularity->amplitudeDays,
                'regularity' => $regularity->regularity->value,
                'sample_size' => $cycleLength->sampleSize,
            ],
            'history' => $history,
            'forecast' => $forecast,
        ]);
    }
}
