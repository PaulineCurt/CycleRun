<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use Illuminate\Http\Request;

class CycleController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->cycles()->orderBy('started_on')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'started_on' => ['required', 'date'],
            'menses_ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
        ]);

        $cycle = $request->user()->cycles()->create($data);

        return response()->json($cycle, 201);
    }

    public function update(Request $request, Cycle $cycle)
    {
        abort_unless($cycle->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'started_on' => ['sometimes', 'date'],
            // Closure rather than after_or_equal:started_on: `started_on` is only
            // `sometimes` present here, and the built-in date rule mishandles a
            // reference field that's missing from this request's input (it isn't
            // read from the existing model) — compare against whichever is current.
            'menses_ended_on' => ['nullable', 'date', function ($attribute, $value, $fail) use ($request, $cycle) {
                if ($value === null) {
                    return;
                }
                $startedOn = $request->input('started_on', $cycle->started_on->toDateString());
                if ($value < $startedOn) {
                    $fail('La fin des règles doit être à partir du début du cycle.');
                }
            }],
        ]);

        $cycle->update($data);

        return response()->json($cycle);
    }

    public function destroy(Request $request, Cycle $cycle)
    {
        abort_unless($cycle->user_id === $request->user()->id, 404);

        $cycle->delete();

        return response()->json(null, 204);
    }
}
