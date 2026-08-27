<?php

namespace App\Http\Controllers;

use App\Enums\Methodology;
use App\Enums\ProgramStatus;
use App\Enums\SessionStatus;
use App\Models\Program;
use App\Models\TrainingSession;
use App\Services\Training\BuildParams;
use App\Services\Training\CycleAdaptationDecorator;
use App\Services\Training\FeasibilityResult;
use App\Services\Training\LevelSource;
use App\Services\Training\ProgramBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Moteur B API. `store` runs the deterministic builder and persists the plan;
 * `show` re-derives the cycle adaptation overlay at read time (§6) — it is never
 * stored, so dropping it always gives back the plain generated plan.
 */
class ProgramController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->programs()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Program $program) => $this->summary($program));
    }

    public function store(Request $request, ProgramBuilder $builder)
    {
        $data = $request->validate([
            'level_input' => ['required', 'array'],
            'level_input.kind' => ['required', 'in:field_test,race_result'],
            'level_input.vma_kmh' => ['nullable', 'numeric', 'gt:0'],
            'level_input.half_cooper_m' => ['nullable', 'numeric', 'gt:0'],
            'level_input.distance_m' => ['nullable', 'integer', 'gt:0'],
            'level_input.time_s' => ['nullable', 'integer', 'gt:0'],
            'goal_time_s' => ['nullable', 'integer', 'gt:0'],
            'duration_weeks' => ['required', 'integer', 'min:6', 'max:24'],
            'days_per_week' => ['required', 'integer', 'min:2', 'max:4'],
            'start_date' => ['required', 'date'],
            'health_flags' => ['sometimes', 'array'],
        ]);

        // v1 is 5 km road only (locked scope). The engine accepts other distances;
        // the API deliberately does not expose that yet.
        $params = new BuildParams(
            levelInput: $data['level_input'],
            goalDistanceM: 5000,
            goalTimeS: $data['goal_time_s'] ?? null,
            durationWeeks: $data['duration_weeks'],
            daysPerWeek: $data['days_per_week'],
            healthFlags: $data['health_flags'] ?? [],
            startDate: CarbonImmutable::parse($data['start_date']),
        );

        try {
            $result = $builder->build($params);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $methodology = $result->level->source === LevelSource::RaceResult
            ? Methodology::Vdot
            : Methodology::Vma;

        $program = DB::transaction(function () use ($request, $params, $result, $methodology) {
            $program = $request->user()->programs()->create([
                'goal_distance_m' => $params->goalDistanceM,
                'goal_time_s' => $params->goalTimeS,
                'methodology' => $methodology,
                'level_input' => $params->levelInput,
                'paces' => $result->level->paces,
                'duration_weeks' => $params->durationWeeks,
                'days_per_week' => $params->daysPerWeek,
                'start_date' => $params->startDate->toDateString(),
                'status' => ProgramStatus::Draft,
                'feasibility' => $this->feasibility($result->feasibility),
                'warnings' => $result->warnings,
            ]);

            foreach ($result->sessions as $draft) {
                $program->sessions()->create([
                    'planned_date' => $draft->plannedDate->toDateString(),
                    'week_index' => $draft->weekIndex,
                    'type' => $draft->type,
                    'planned_load' => $draft->plannedLoad,
                    'target_pace_min_s' => $draft->targetPaceMinS,
                    'target_pace_max_s' => $draft->targetPaceMaxS,
                    'base_generated' => $draft->toArray(),
                    'status' => SessionStatus::Planned,
                ]);
            }

            return $program;
        });

        $program->load(['sessions' => fn ($query) => $query->orderBy('planned_date')]);

        return response()->json($this->detail($request, $program), 201);
    }

    public function show(Request $request, Program $program)
    {
        abort_unless($program->user_id === $request->user()->id, 404);

        $program->load(['sessions' => fn ($query) => $query->orderBy('planned_date')]);

        return response()->json($this->detail($request, $program));
    }

    public function update(Request $request, Program $program)
    {
        abort_unless($program->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ProgramStatus::class)],
        ]);

        $program->update($data);

        return response()->json($this->summary($program->refresh()));
    }

    public function destroy(Request $request, Program $program)
    {
        abort_unless($program->user_id === $request->user()->id, 404);

        $program->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function summary(Program $program): array
    {
        return [
            'id' => $program->id,
            'goal_distance_m' => $program->goal_distance_m,
            'goal_time_s' => $program->goal_time_s,
            'methodology' => $program->methodology->value,
            'duration_weeks' => $program->duration_weeks,
            'days_per_week' => $program->days_per_week,
            'start_date' => $program->start_date->toDateString(),
            'status' => $program->status->value,
            'created_at' => $program->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Request $request, Program $program): array
    {
        $sessions = $program->sessions;

        // §6: the cycle adaptation is layered on here, at read time — not persisted.
        app(CycleAdaptationDecorator::class)->decorate($request->user(), $sessions);

        return array_merge($this->summary($program), [
            'paces' => $program->paces,
            'level_input' => $program->level_input,
            'feasibility' => $program->feasibility,
            'warnings' => $program->warnings ?? [],
            'sessions' => $sessions->map(fn (TrainingSession $session) => [
                'id' => $session->id,
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
            ])->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function feasibility(FeasibilityResult $feasibility): array
    {
        return [
            'feasible' => $feasibility->feasible,
            'projected_time_s' => $feasibility->projectedTimeS,
            'achievable_time_s' => $feasibility->achievableTimeS,
            'suggested_goal_time_s' => $feasibility->suggestedGoalTimeS,
            'suggested_duration_weeks' => $feasibility->suggestedDurationWeeks,
            'alert' => $feasibility->alert,
        ];
    }
}
