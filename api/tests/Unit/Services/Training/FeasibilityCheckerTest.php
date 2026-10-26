<?php

namespace Tests\Unit\Services\Training;

use App\Enums\Level;
use App\Services\Training\FeasibilityChecker;
use App\Services\Training\LevelNormalizer;
use App\Services\Training\LevelProfile;
use App\Services\Training\LevelProfiles;
use App\Services\Training\NormalizedLevel;
use App\Services\Training\Vdot;
use PHPUnit\Framework\TestCase;

class FeasibilityCheckerTest extends TestCase
{
    private FeasibilityChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new FeasibilityChecker;
    }

    private function profile(Level $level = Level::Intermediaire): LevelProfile
    {
        return LevelProfiles::for($level);
    }

    private function fieldTestLevel(float $vma = 14.0, Level $level = Level::Intermediaire): NormalizedLevel
    {
        return (new LevelNormalizer)->normalize($level, ['kind' => 'field_test', 'vma_kmh' => $vma]);
    }

    public function test_no_goal_time_is_always_feasible_and_still_projects(): void
    {
        $result = $this->checker->check($this->profile(), $this->fieldTestLevel(), 5000, null, 12);

        $this->assertTrue($result->feasible);
        $this->assertNull($result->alert);
        // Derived off the D&G drop-off curve, not a flat fraction of VMA.
        $this->assertEqualsWithDelta(1345, $result->projectedTimeS, 3);
        $this->assertLessThan($result->projectedTimeS, $result->achievableTimeS);
    }

    public function test_curve_projection_is_consistent_with_an_independently_computed_vdot(): void
    {
        $vma = 14.0;
        $projected = $this->checker->check($this->profile(), $this->fieldTestLevel($vma), 5000, null, 12)
            ->projectedTimeS;

        // A 5 km run in the projected time should imply ~the same VDOT as the
        // aerobic cost at vVO2max — the whole engine reads one curve.
        $this->assertEqualsWithDelta(
            Vdot::vo2AtVelocity($vma * 1000 / 60),
            Vdot::fromRace(5000, $projected),
            0.3,
        );
    }

    public function test_a_relaxed_goal_is_feasible(): void
    {
        $result = $this->checker->check($this->profile(), $this->fieldTestLevel(), 5000, 1300, 12);

        $this->assertTrue($result->feasible);
        $this->assertNull($result->suggestedGoalTimeS);
    }

    public function test_an_aggressive_goal_is_flagged_with_a_longer_plan_suggestion(): void
    {
        $result = $this->checker->check($this->profile(), $this->fieldTestLevel(), 5000, 1220, 12);

        $this->assertFalse($result->feasible);
        $this->assertNotNull($result->alert);
        $this->assertSame($result->achievableTimeS, $result->suggestedGoalTimeS);
        $this->assertNotNull($result->suggestedDurationWeeks);
        $this->assertGreaterThan(12, $result->suggestedDurationWeeks);
    }

    public function test_an_out_of_reach_goal_gives_no_duration_suggestion(): void
    {
        $result = $this->checker->check($this->profile(), $this->fieldTestLevel(), 5000, 1120, 12);

        $this->assertFalse($result->feasible);
        $this->assertNull($result->suggestedDurationWeeks);
        $this->assertStringContainsString('hors de portée', $result->alert);
    }

    public function test_improvement_bounds_come_from_the_level_profile(): void
    {
        // Same runner, same goal — only the declared level differs. The confirmée
        // ceiling (8 %) puts the goal out of reach; the débutante ceiling (20 %)
        // keeps it reachable with a longer plan.
        $goal = 1150;

        $confirmee = $this->checker->check(
            $this->profile(Level::Confirmee),
            $this->fieldTestLevel(14.0, Level::Confirmee),
            5000,
            $goal,
            12,
        );
        $debutante = $this->checker->check(
            $this->profile(Level::Debutante),
            $this->fieldTestLevel(14.0, Level::Debutante),
            5000,
            $goal,
            12,
        );

        $this->assertNull($confirmee->suggestedDurationWeeks);
        $this->assertStringContainsString('hors de portée', $confirmee->alert);
        $this->assertNotNull($debutante->suggestedDurationWeeks);
    }

    public function test_race_result_level_projects_via_riegel(): void
    {
        $level = (new LevelNormalizer)->normalize(Level::Intermediaire, [
            'kind' => 'race_result',
            'distance_m' => 10000,
            'time_s' => 2800,
        ]);

        $result = $this->checker->check($this->profile(), $level, 5000, null, 12);

        // Riegel 10k 46:40 -> 5k ≈ 2800 · 0.5^1.06 ≈ 1343 s
        $this->assertEqualsWithDelta(1343, $result->projectedTimeS, 3);
    }

    public function test_is_deterministic(): void
    {
        $level = $this->fieldTestLevel();

        $this->assertEquals(
            $this->checker->check($this->profile(), $level, 5000, 1220, 12),
            $this->checker->check($this->profile(), $level, 5000, 1220, 12),
        );
    }

    public function test_non_positive_weeks_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->checker->check($this->profile(), $this->fieldTestLevel(), 5000, null, 0);
    }
}
