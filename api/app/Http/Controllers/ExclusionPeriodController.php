<?php

namespace App\Http\Controllers;

use App\Enums\ExclusionType;
use App\Models\ExclusionPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExclusionPeriodController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->exclusionPeriods()->orderBy('started_on')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(ExclusionType::class)],
            'started_on' => ['required', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
        ]);

        $exclusionPeriod = $request->user()->exclusionPeriods()->create($data);

        return response()->json($exclusionPeriod, 201);
    }

    public function update(Request $request, ExclusionPeriod $exclusionPeriod)
    {
        abort_unless($exclusionPeriod->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'type' => ['sometimes', Rule::enum(ExclusionType::class)],
            'started_on' => ['sometimes', 'date'],
            'ended_on' => ['nullable', 'date'],
        ]);

        $exclusionPeriod->update($data);

        return response()->json($exclusionPeriod);
    }

    public function destroy(Request $request, ExclusionPeriod $exclusionPeriod)
    {
        abort_unless($exclusionPeriod->user_id === $request->user()->id, 404);

        $exclusionPeriod->delete();

        return response()->json(null, 204);
    }
}
