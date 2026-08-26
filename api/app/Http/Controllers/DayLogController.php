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
        return $request->user()->dayLogs()->orderBy('date')->get();
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
            'symptoms' => ['nullable', 'array'],
            'symptoms.*' => ['string'],
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
            'symptoms' => ['nullable', 'array'],
            'symptoms.*' => ['string'],
            'note' => ['nullable', 'string'],
        ]);

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
