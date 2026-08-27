<?php

namespace App\Http\Controllers;

use App\Services\Cycle\CycleCalculator;
use App\Services\Cycle\PhaseResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class CycleStatusController extends Controller
{
    public function __invoke(Request $request, CycleCalculator $calculator, PhaseResolver $resolver)
    {
        $data = $request->validate([
            'date' => ['sometimes', 'date'],
        ]);

        $user = $request->user();
        $asOf = isset($data['date']) ? CarbonImmutable::parse($data['date']) : CarbonImmutable::today();

        $cycles = $user->cycles()->orderBy('started_on')->get();
        $exclusionPeriods = $user->exclusionPeriods;

        $currentCycle = $cycles->last(fn ($cycle) => $cycle->started_on->lessThanOrEqualTo($asOf));

        if ($currentCycle === null) {
            return response()->json(['status' => 'no_data']);
        }

        $cycleLength = $calculator->cycleLength($cycles, $user->typical_cycle_length, $exclusionPeriods, $user->cycle_length_override);
        $mensesLength = $calculator->mensesLength($cycles, $user->typical_menses_length, $user->menses_length_override);
        $birthDate = $user->birth_date?->toImmutable();
        $regularity = $calculator->regularity($cycles, $birthDate, $asOf, $exclusionPeriods);
        $frequency = $calculator->frequency($cycles, $exclusionPeriods, $user->cycle_length_override);
        $cycleStart = $currentCycle->started_on->toImmutable();

        $phase = $resolver->resolve(
            date: $asOf,
            cycleStart: $cycleStart,
            cycleLength: $cycleLength,
            mensesLength: $mensesLength,
            regularity: $regularity,
        );

        return response()->json([
            'status' => 'ok',
            'cycle_day' => $cycleStart->diffInDays($asOf) + 1,
            'phase' => $phase->phase?->value,
            'predictable' => $phase->predictable,
            'predicted_next_start' => $phase->predictedNextStart->toDateString(),
            'ovulation_window' => [
                'start' => $phase->ovulationWindowStart->toDateString(),
                'end' => $phase->ovulationWindowEnd->toDateString(),
            ],
            'next_start_range' => $phase->nextStartRangeStart ? [
                'start' => $phase->nextStartRangeStart->toDateString(),
                'end' => $phase->nextStartRangeEnd->toDateString(),
            ] : null,
            'low_confidence' => $phase->lowConfidence,
            'regularity' => $regularity->regularity->value,
            'frequency' => $frequency->value,
            'overdue' => $resolver->isOverdue($asOf, $cycleStart, $cycleLength, $regularity->amplitudeDays),
            'severely_overdue' => $resolver->isSeverelyOverdue($asOf, $cycleStart),
        ]);
    }
}
