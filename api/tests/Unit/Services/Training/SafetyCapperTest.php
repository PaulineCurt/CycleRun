<?php

namespace Tests\Unit\Services\Training;

use App\Services\Training\Periodizer;
use App\Services\Training\SafetyCapper;
use App\Services\Training\SessionDraft;
use App\Enums\SessionType;
use App\Services\Training\Track;
use App\Services\Training\WeekBlock;
use App\Services\Training\Mesocycle;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SafetyCapperTest extends TestCase
{
    private function session(SessionType $type, array $load): SessionDraft
    {
        return new SessionDraft(CarbonImmutable::parse('2026-09-07'), 0, $type, $load, 300, 360);
    }

    public function test_no_flags_leaves_sessions_untouched(): void
    {
        $sessions = [$this->session(SessionType::Easy, ['mode' => 'duration', 'duration_s' => 2400])];
        $blocks = (new Periodizer())->build(8, 3, Track::Continuous);

        $result = (new SafetyCapper())->apply($sessions, [], $blocks);

        $this->assertEquals($sessions, $result['sessions']);
    }

    public function test_max_session_min_caps_duration_and_warns(): void
    {
        $sessions = [$this->session(SessionType::Long, ['mode' => 'duration', 'duration_s' => 5400])];

        $result = (new SafetyCapper())->apply($sessions, ['max_session_min' => 60], []);

        $this->assertSame(3600, $result['sessions'][0]->plannedLoad['duration_s']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_no_high_intensity_downgrades_interval_to_tempo_including_the_pace_band(): void
    {
        $sessions = [
            $this->session(SessionType::Interval, ['mode' => 'intervals', 'reps' => 8, 'work_s' => 120, 'recovery_s' => 60]),
        ];
        $paces = ['tempo' => [280, 300], 'interval' => [240, 260]];

        $result = (new SafetyCapper())->apply($sessions, ['no_high_intensity' => true], [], $paces);

        $this->assertSame(SessionType::Tempo, $result['sessions'][0]->type);
        $this->assertSame('duration', $result['sessions'][0]->plannedLoad['mode']);
        // the pace band must move to tempo, not keep the near-max VMA band
        $this->assertSame(280, $result['sessions'][0]->targetPaceMinS);
        $this->assertSame(300, $result['sessions'][0]->targetPaceMaxS);
    }

    public function test_low_impact_turns_the_quality_session_into_cross_training(): void
    {
        $sessions = [
            $this->session(SessionType::Threshold, ['mode' => 'duration', 'duration_s' => 3000, 'work_s' => 1800]),
            $this->session(SessionType::Easy, ['mode' => 'duration', 'duration_s' => 2400]),
        ];

        $result = (new SafetyCapper())->apply($sessions, ['low_impact' => true], []);

        $this->assertSame(SessionType::Cross, $result['sessions'][0]->type);
        $this->assertSame(SessionType::Easy, $result['sessions'][1]->type);
    }

    public function test_flags_a_week_that_jumps_more_than_10_percent(): void
    {
        $blocks = [
            new WeekBlock(0, Mesocycle::Base, false, 100, 5, 95),
            new WeekBlock(1, Mesocycle::Base, false, 130, 7, 123), // +30 %
        ];

        $result = (new SafetyCapper())->apply([], [], $blocks);

        $this->assertNotEmpty(array_filter($result['warnings'], fn ($w) => str_contains($w, 'Semaine 1')));
    }

    public function test_is_deterministic(): void
    {
        $sessions = [$this->session(SessionType::Long, ['mode' => 'duration', 'duration_s' => 5400])];

        $this->assertEquals(
            (new SafetyCapper())->apply($sessions, ['max_session_min' => 60], []),
            (new SafetyCapper())->apply($sessions, ['max_session_min' => 60], []),
        );
    }
}
