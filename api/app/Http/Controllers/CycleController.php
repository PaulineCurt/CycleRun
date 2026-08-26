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
            'menses_ended_on' => ['nullable', 'date'],
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
