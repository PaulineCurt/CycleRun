<?php

namespace App\Http\Controllers;

use App\Enums\Flow;
use App\Models\DayLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DayLogController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'date' => ['sometimes', 'date'],
        ]);

        return $request->user()->dayLogs()
            ->when(isset($data['date']), fn ($query) => $query->whereDate('date', $data['date']))
            ->orderBy('date')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => [
                'required',
                'date',
                Rule::unique('day_logs')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'flow' => ['nullable', Rule::enum(Flow::class)],
            'pain' => ['nullable', 'integer', 'between:0,3'],
            'energy' => ['nullable', 'integer', 'between:1,5'],
            // Map of symptom key => intensity (0 = asked and none, 1-3 = intensity felt).
            'symptoms' => ['nullable', 'array'],
            'symptoms.*' => ['integer', 'between:0,3'],
            'note' => ['nullable', 'string'],
        ]);

        $dayLog = $request->user()->dayLogs()->create($data);

        return response()->json($dayLog, 201);
    }

    public function update(Request $request, DayLog $dayLog)
    {
        abort_unless($dayLog->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'date' => [
                'sometimes',
                'date',
                Rule::unique('day_logs')->ignore($dayLog->id)->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'flow' => ['nullable', Rule::enum(Flow::class)],
            'pain' => ['nullable', 'integer', 'between:0,3'],
            'energy' => ['nullable', 'integer', 'between:1,5'],
            // Map of symptom key => intensity (0 = asked and none, 1-3 = intensity felt).
            'symptoms' => ['nullable', 'array'],
            'symptoms.*' => ['integer', 'between:0,3'],
            'note' => ['nullable', 'string'],
        ]);

        // Merge rather than replace: the client sends one changed symptom key at a
        // time (or a partial map), and a wholesale replace would race against other
        // in-flight taps and silently wipe out symptoms logged moments earlier.
        if (array_key_exists('symptoms', $data)) {
            $data['symptoms'] = array_merge($dayLog->symptoms ?? [], $data['symptoms']);
        }

        $dayLog->update($data);

        return response()->json($dayLog);
    }

    public function destroy(Request $request, DayLog $dayLog)
    {
        abort_unless($dayLog->user_id === $request->user()->id, 404);

        $dayLog->delete();

        return response()->json(null, 204);
    }
}
