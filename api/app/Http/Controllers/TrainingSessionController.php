<?php

namespace App\Http\Controllers;

use App\Enums\SessionStatus;
use App\Models\TrainingSession;
use App\Services\Training\CycleAdaptationDecorator;
use App\Services\Training\CycleAdapter;
use App\Services\Training\CyclePhaseProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read a program's sessions and record what actually happened (status + `actual`
 * feedback). Ownership runs through the parent program (sessions carry no user_id).
 */
class TrainingSessionController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'program_id' => ['required', 'integer'],
        ]);

        $program = $request->user()->programs()->find($data['program_id']);
        abort_if($program === null, 404);

        return $program->sessions()
            ->orderBy('planned_date')
            ->get()
            ->map(fn (TrainingSession $session) => $this->serialize($session));
    }

    public function show(Request $request, TrainingSession $trainingSession)
    {
        abort_unless($trainingSession->program->user_id === $request->user()->id, 404);

        // §6: overlay the cycle adaptation at read time (not persisted).
        app(CycleAdaptationDecorator::class)->decorate($request->user(), [$trainingSession]);

        return response()->json($this->serialize($trainingSession));
    }

    public function update(Request $request, TrainingSession $trainingSession)
    {
        abort_unless($trainingSession->program->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(SessionStatus::class)],
            'planned_date' => ['sometimes', 'date'],
            'actual' => ['sometimes', 'nullable', 'array'],
            'actual.rpe' => ['sometimes', 'nullable', 'integer', 'between:1,10'],
            'actual.duration_s' => ['sometimes', 'nullable', 'integer', 'gt:0'],
            'actual.distance_m' => ['sometimes', 'nullable', 'integer', 'gt:0'],
            'actual.note' => ['sometimes', 'nullable', 'string'],
        ]);

        // Merge `actual`, never replace — a later PATCH that only carries the RPE
        // must not wipe a previously logged distance/duration (same rule as
        // day-logs' symptoms). `actual: null` still clears it outright.
        if (array_key_exists('actual', $data) && $data['actual'] !== null) {
            $data['actual'] = array_merge($trainingSession->actual ?? [], $data['actual']);
        }

        // Leaving 'planned' freezes the cycle adaptation that was in effect for
        // this date onto the row — history stops re-deriving from a moving cycle.
        $leavingPlanned = isset($data['status'])
            && $data['status'] !== SessionStatus::Planned->value
            && $trainingSession->status === SessionStatus::Planned
            && $trainingSession->adaptation === null;

        if ($leavingPlanned) {
            $snapshot = app(CyclePhaseProvider::class)
                ->forDate($request->user(), $trainingSession->planned_date->toImmutable());
            $data['adaptation'] = app(CycleAdapter::class)
                ->adapt($snapshot, $trainingSession->type)?->toArray();
        }

        $trainingSession->update($data);

        return response()->json($this->serialize($trainingSession->refresh()));
    }

    /** @return array<string, mixed> */
    private function serialize(TrainingSession $session): array
    {
        return [
            'id' => $session->id,
            'program_id' => $session->program_id,
            'planned_date' => $session->planned_date->toDateString(),
            'week_index' => $session->week_index,
            'type' => $session->type->value,
            'planned_load' => $session->planned_load,
            'target_pace_min_s' => $session->target_pace_min_s,
            'target_pace_max_s' => $session->target_pace_max_s,
            'status' => $session->status->value,
            'cycle' => $session->cycle ?? null,
            'adaptation' => $session->adaptation,
            'adapted' => (bool) ($session->adapted ?? false),
            'postponed' => (bool) ($session->postponed ?? false),
            'base' => $session->base_generated,
            'actual' => $session->actual,
        ];
    }
}
