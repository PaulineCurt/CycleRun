<?php

namespace App\Http\Controllers;

use App\Services\Cycle\CycleCalculator;
use App\Services\Cycle\EstimateSource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request, CycleCalculator $calculator)
    {
        $user = $request->user();
        $cycles = $user->cycles()->orderBy('started_on')->get();
        $exclusionPeriods = $user->exclusionPeriods;

        $cycleLength = $calculator->cycleLength($cycles, $user->typical_cycle_length, $exclusionPeriods);
        $mensesLength = $calculator->mensesLength($cycles, $user->typical_menses_length);

        return response()->json([
            ...$user->toArray(),
            // Once real history exists, the declared fallback is moot — the front locks
            // the field instead of letting a stale manual value contradict measured data.
            'cycle_length_measured' => $cycleLength->source === EstimateSource::Measured,
            'cycle_length_average' => (int) round($cycleLength->days),
            'menses_length_measured' => $mensesLength->source === EstimateSource::Measured,
            'menses_length_average' => (int) round($mensesLength->days),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'typical_cycle_length' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:255'],
            'typical_menses_length' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:255'],
        ]);

        $request->user()->update($data);

        return response()->json($request->user());
    }
}
