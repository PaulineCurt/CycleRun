<?php

namespace Tests\Unit\Services\Training;

use App\Services\Training\LevelNormalizer;
use App\Services\Training\Periodizer;
use App\Services\Training\SessionMaterializer;
use App\Enums\SessionType;
use App\Services\Training\Track;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SessionMaterializerTest extends TestCase
{
    private SessionMaterializer $materializer;
    private array $paces;

    protected function setUp(): void
    {
        $this->materializer = new SessionMaterializer();
        $this->paces = (new LevelNormalizer())->normalize(['kind' => 'field_test', 'vma_kmh' => 14.0])->paces;
    }

    private function build(int $weeks, int $days, Track $track): array
    {
        $blocks = (new Periodizer())->build($weeks, $days, $track);

        return $this->materializer->materialize(
            $blocks,
            $this->paces,
            CarbonImmutable::parse('2026-09-07'), // a Monday
            $days,
            $track,
            $weeks,
        );
    }

    public function test_emits_days_per_week_sessions_for_every_week(): void
    {
        $sessions = $this->build(12, 3, Track::Continuous);

        $this->assertCount(12 * 3, $sessions);
    }

    public function test_sessions_are_dated_on_fixed_weekday_offsets(): void
    {
        $sessions = $this->build(4, 3, Track::Continuous);
        $start = CarbonImmutable::parse('2026-09-07');

        // offsets for 3 days/week are [0, 2, 4]; same pattern every week
        foreach ([0, 1, 2, 3] as $week) {
            $weekSessions = array_values(array_filter($sessions, fn ($s) => $s->weekIndex === $week));
            $this->assertSame($start->addDays($week * 7 + 0)->toDateString(), $weekSessions[0]->plannedDate->toDateString());
            $this->assertSame($start->addDays($week * 7 + 2)->toDateString(), $weekSessions[1]->plannedDate->toDateString());
            $this->assertSame($start->addDays($week * 7 + 4)->toDateString(), $weekSessions[2]->plannedDate->toDateString());
        }
    }

    public function test_continuous_week_has_a_long_run_as_its_last_session(): void
    {
        $sessions = $this->build(12, 3, Track::Continuous);
        $week1 = array_values(array_filter($sessions, fn ($s) => $s->weekIndex === 1));

        $this->assertSame(SessionType::Long, end($week1)->type);
    }

    public function test_base_weeks_have_no_hard_session_development_weeks_do(): void
    {
        $sessions = $this->build(12, 3, Track::Continuous);

        $base = array_filter($sessions, fn ($s) => $s->weekIndex === 0);
        foreach ($base as $s) {
            $this->assertNotContains($s->type, [SessionType::Interval, SessionType::Threshold]);
        }

        $hardTypesLate = array_filter(
            $sessions,
            fn ($s) => $s->weekIndex >= 7 && in_array($s->type, [SessionType::Interval, SessionType::Threshold], true),
        );
        $this->assertNotEmpty($hardTypesLate);
    }

    public function test_easy_sessions_carry_the_easy_pace_band(): void
    {
        $sessions = $this->build(8, 3, Track::Continuous);
        $easy = array_values(array_filter($sessions, fn ($s) => $s->type === SessionType::Easy))[0];

        $this->assertSame($this->paces['easy'][0], $easy->targetPaceMinS);
        $this->assertSame($this->paces['easy'][1], $easy->targetPaceMaxS);
    }

    public function test_run_walk_track_starts_with_run_walk_sessions_and_transitions_to_continuous(): void
    {
        $sessions = $this->build(16, 3, Track::RunWalk);

        $this->assertSame(SessionType::RunWalk, $sessions[0]->type);
        $this->assertSame('run_walk', $sessions[0]->plannedLoad['mode']);

        // by the end the plan has switched to continuous running
        $lastWeek = array_values(array_filter($sessions, fn ($s) => $s->weekIndex === 15));
        foreach ($lastWeek as $s) {
            $this->assertNotSame(SessionType::RunWalk, $s->type);
        }
    }

    public function test_run_walk_intervals_progress_more_running_less_walking(): void
    {
        $sessions = $this->build(16, 3, Track::RunWalk);
        $first = $sessions[0];
        $laterRunWalk = null;
        foreach ($sessions as $s) {
            if ($s->type === SessionType::RunWalk && $s->weekIndex > $first->weekIndex) {
                $laterRunWalk = $s;
            }
        }

        $this->assertNotNull($laterRunWalk);
        $this->assertGreaterThan($first->plannedLoad['run_s'], $laterRunWalk->plannedLoad['run_s']);
        $this->assertLessThanOrEqual($first->plannedLoad['walk_s'], $laterRunWalk->plannedLoad['walk_s']);
    }

    public function test_supports_two_and_four_days_per_week(): void
    {
        $this->assertCount(10 * 2, $this->build(10, 2, Track::Continuous));
        $this->assertCount(10 * 4, $this->build(10, 4, Track::Continuous));
    }

    public function test_two_day_week_still_places_the_hard_session_and_never_a_zero_length_one(): void
    {
        $sessions = $this->build(12, 2, Track::Continuous);

        foreach ($sessions as $session) {
            $this->assertGreaterThan(0, $session->totalSeconds(), 'no empty session');
        }

        // a late development week on a 2-day plan must still carry a quality session
        $late = array_filter(
            $sessions,
            fn ($s) => $s->weekIndex >= 8
                && in_array($s->type, [SessionType::Interval, SessionType::Threshold], true),
        );
        $this->assertNotEmpty($late);
    }

    public function test_is_deterministic(): void
    {
        $this->assertEquals($this->build(12, 3, Track::Continuous), $this->build(12, 3, Track::Continuous));
    }
}
