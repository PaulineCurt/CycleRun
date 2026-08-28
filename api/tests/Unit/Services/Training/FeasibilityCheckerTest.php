<?php

namespace Tests\Unit\Services\Training;

use App\Services\Training\FeasibilityChecker;
use App\Services\Training\LevelNormalizer;
use App\Services\Training\NormalizedLevel;
use PHPUnit\Framework\TestCase;

class FeasibilityCheckerTest extends TestCase
{
    private FeasibilityChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new FeasibilityChecker();
    }

    private function fieldTestLevel(float $vma = 14.0): NormalizedLevel
    {
        return (new LevelNormalizer())->normalize(['kind' => 'field_test', 'vma_kmh' => $vma]);
    }

    public function test_no_goal_time_is_always_feasible_and_still_projects(): void
    {
        $result = $this->checker->check($this->fieldTestLevel(), 5000, null, 12);

        $this->assertTrue($result->feasible);
        $this->assertNull($result->alert);
        $this->assertEqualsWithDelta(1495, $result->projectedTimeS, 2);
        $this->assertLessThan($result->projectedTimeS, $result->achievableTimeS);
    }

    public function test_a_relaxed_goal_is_feasible(): void
    {
        $result = $this->checker->check($this->fieldTestLevel(), 5000, 1500, 12);

        $this->assertTrue($result->feasible);
        $this->assertNull($result->suggestedGoalTimeS);
    }

    public function test_an_aggressive_goal_is_flagged_with_a_longer_plan_suggestion(): void
    {
        $result = $this->checker->check($this->fieldTestLevel(), 5000, 1350, 12);

        $this->assertFalse($result->feasible);
        $this->assertNotNull($result->alert);
        $this->assertSame($result->achievableTimeS, $result->suggestedGoalTimeS);
        $this->assertNotNull($result->suggestedDurationWeeks);
        $this->assertGreaterThan(12, $result->suggestedDurationWeeks);
    }

    public function test_an_out_of_reach_goal_gives_no_duration_suggestion(): void
    {
        $result = $this->checker->check($this->fieldTestLevel(), 5000, 1250, 12);

        $this->assertFalse($result->feasible);
        $this->assertNull($result->suggestedDurationWeeks);
        $this->assertStringContainsString('hors de portée', $result->alert);
    }

    public function test_race_result_level_projects_via_riegel(): void
    {
        $level = (new LevelNormalizer())->normalize([
            'kind' => 'race_result',
            'distance_m' => 10000,
            'time_s' => 2800,
        ]);

        $result = $this->checker->check($level, 5000, null, 12);

        // Riegel 10k 46:40 -> 5k ≈ 2800 · 0.5^1.06 ≈ 1343 s
        $this->assertEqualsWithDelta(1343, $result->projectedTimeS, 3);
    }

    public function test_is_deterministic(): void
    {
        $level = $this->fieldTestLevel();

        $this->assertEquals(
            $this->checker->check($level, 5000, 1350, 12),
            $this->checker->check($level, 5000, 1350, 12),
        );
    }

    public function test_non_positive_weeks_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->checker->check($this->fieldTestLevel(), 5000, null, 0);
    }
}
