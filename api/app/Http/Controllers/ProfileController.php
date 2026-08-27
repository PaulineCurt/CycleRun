<?php

namespace App\Http\Controllers;

use App\Enums\Avatar;
use App\Models\User;
use App\Services\Cycle\CycleCalculator;
use App\Services\Cycle\EstimateSource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request, CycleCalculator $calculator)
    {
        return response()->json($this->buildProfile($request->user(), $calculator));
    }

    public function update(Request $request, CycleCalculator $calculator)
    {
        $data = $request->validate([
            'avatar' => ['sometimes', 'nullable', Rule::enum(Avatar::class)],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'typical_cycle_length' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:255'],
            'typical_menses_length' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:255'],
            // Tighter than the declared fallback above: an override permanently wins
            // over real measured data and is never auto-superseded, so a wildly
            // implausible value (e.g. 1) would silently corrupt every prediction with
            // no measured history ever around to contradict it. Bounds mirror
            // CycleCalculator's own outlier window for cycle length (10-60 days);
            // menses length gets a proportionally sane clinical ceiling.
            'cycle_length_override' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:60'],
            'menses_length_override' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $user = $request->user();
        $user->update($data);

        // Same shape as show(): any partial update (avatar, an override, ...) must
        // return the full enriched profile, not the raw model — a caller that trusts
        // this response as the new profile state would otherwise lose
        // cycle_length_measured/average and render the locked fields as editable again.
        return response()->json($this->buildProfile($user, $calculator));
    }

    private function buildProfile(User $user, CycleCalculator $calculator): array
    {
        $cycles = $user->cycles()->orderBy('started_on')->get();
        $exclusionPeriods = $user->exclusionPeriods;

        // Deliberately computed WITHOUT the override here: this reflects what the real
        // data says (measured/declared/default), independent of whether a manual
        // override is also active. The override's effect on actual predictions is
        // applied separately in CycleStatus/CycleCalendar/CycleAnalysis controllers.
        $cycleLength = $calculator->cycleLength($cycles, $user->typical_cycle_length, $exclusionPeriods);
        $mensesLength = $calculator->mensesLength($cycles, $user->typical_menses_length);

        return [
            ...$user->toArray(),
            // Once real history exists, the declared fallback is moot — the front locks
            // the field instead of letting a stale manual value contradict measured data.
            'cycle_length_measured' => $cycleLength->source === EstimateSource::Measured,
            'cycle_length_average' => (int) round($cycleLength->days),
            'menses_length_measured' => $mensesLength->source === EstimateSource::Measured,
            'menses_length_average' => (int) round($mensesLength->days),
        ];
    }
}
