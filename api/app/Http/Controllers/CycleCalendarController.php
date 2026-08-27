<?php

namespace App\Http\Controllers;

use App\Services\Cycle\CycleCalculator;
use App\Services\Cycle\PhaseResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class CycleCalendarController extends Controller
{
    public function __invoke(Request $request, CycleCalculator $calculator, PhaseResolver $resolver)
    {
        $data = $request->validate([
            'month' => ['sometimes', 'date_format:Y-m'],
        ]);

        $month = isset($data['month'])
            ? CarbonImmutable::createFromFormat('Y-m', $data['month'])->startOfMonth()
            : CarbonImmutable::today()->startOfMonth();

        $user = $request->user();
        $cycles = $user->cycles()->orderBy('started_on')->get();
        $exclusionPeriods = $user->exclusionPeriods;

        $cycleLength = $calculator->cycleLength($cycles, $user->typical_cycle_length, $exclusionPeriods, $user->cycle_length_override);
        $mensesLength = $calculator->mensesLength($cycles, $user->typical_menses_length, $user->menses_length_override);
        $birthDate = $user->birth_date?->toImmutable();
        $regularity = $calculator->regularity($cycles, $birthDate, CarbonImmutable::today(), $exclusionPeriods);

        $days = [];
        $cursor = $month;
        $end = $month->endOfMonth();

        while ($cursor->lessThanOrEqualTo($end)) {
            $currentCycle = $cycles->last(fn ($cycle) => $cycle->started_on->lessThanOrEqualTo($cursor));

            if ($currentCycle === null) {
                $days[] = [
                    'date' => $cursor->toDateString(),
                    'cycle_id' => null,
                    'cycle_day' => null,
                    'is_cycle_start' => false,
                    'is_menses_end' => false,
                    'phase' => null,
                    'predictable' => false,
                    'low_confidence' => true,
                    'is_projected' => false,
                ];
                $cursor = $cursor->addDay();

                continue;
            }

            $phase = $resolver->resolve(
                date: $cursor,
                cycleStart: $currentCycle->started_on->toImmutable(),
                cycleLength: $cycleLength,
                mensesLength: $mensesLength,
                regularity: $regularity,
            );

            // Beyond the predicted next start, no cycle has actually been logged yet. Rather
            // than stop predicting after a single cycle, simulate as many further cycles as
            // needed (same length, repeating) so browsing months ahead still shows a phase —
            // clearly flagged as a projection, never a computed fact.
            $isProjected = $cursor->greaterThanOrEqualTo($phase->predictedNextStart);
            $phaseValue = $phase->phase?->value;
            $predictable = $phase->predictable;

            if ($isProjected) {
                $cycleLengthDays = max(1, (int) round($cycleLength->days));
                $cyclesAhead = intdiv($phase->predictedNextStart->diffInDays($cursor), $cycleLengthDays);
                $projectedCycleStart = $phase->predictedNextStart->addDays($cyclesAhead * $cycleLengthDays);

                $projectedPhase = $resolver->resolve(
                    date: $cursor,
                    cycleStart: $projectedCycleStart,
                    cycleLength: $cycleLength,
                    mensesLength: $mensesLength,
                    regularity: $regularity,
                );

                $phaseValue = $projectedPhase->phase?->value;
                $predictable = $projectedPhase->predictable;
            }

            $days[] = [
                'date' => $cursor->toDateString(),
                'cycle_id' => $currentCycle->id,
                'cycle_day' => $currentCycle->started_on->diffInDays($cursor) + 1,
                'is_cycle_start' => $currentCycle->started_on->equalTo($cursor),
                'is_menses_end' => $currentCycle->menses_ended_on?->equalTo($cursor) ?? false,
                'phase' => $phaseValue,
                'predictable' => $predictable,
                'low_confidence' => $phase->lowConfidence,
                'is_projected' => $isProjected,
            ];

            $cursor = $cursor->addDay();
        }

        return response()->json([
            'month' => $month->format('Y-m'),
            'days' => $days,
        ]);
    }
}
