<?php

namespace Tests\Unit\Services\Cycle;

use App\Models\Cycle;
use App\Services\Cycle\CycleCalculator;
use App\Services\Cycle\EstimateSource;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class CycleCalculatorTest extends TestCase
{
    private function cycle(string $startedOn, ?string $mensesEndedOn = null): Cycle
    {
        return new Cycle([
            'started_on' => $startedOn,
            'menses_ended_on' => $mensesEndedOn,
        ]);
    }

    public function test_menses_length_fencepost_is_inclusive(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([$this->cycle('2026-01-22', '2026-01-25')]);

        $estimate = $calculator->mensesLength($cycles);

        $this->assertSame(4.0, $estimate->days);
    }

    public function test_menses_length_is_measurable_from_a_single_completed_period(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([$this->cycle('2026-01-01', '2026-01-05')]);

        $estimate = $calculator->mensesLength($cycles);

        $this->assertSame(EstimateSource::Measured, $estimate->source);
        $this->assertSame(1, $estimate->sampleSize);
        $this->assertNull($estimate->stdDev);
    }

    public function test_cycle_length_requires_two_started_on_dates(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([$this->cycle('2026-01-01')]);

        $estimate = $calculator->cycleLength($cycles);

        $this->assertSame(EstimateSource::Default, $estimate->source);
        $this->assertSame(28.0, $estimate->days);
    }

    public function test_cycle_length_falls_back_to_declared_value_when_not_measurable(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([$this->cycle('2026-01-01')]);

        $estimate = $calculator->cycleLength($cycles, declared: 30);

        $this->assertSame(EstimateSource::Declared, $estimate->source);
        $this->assertSame(30.0, $estimate->days);
    }

    public function test_cycle_length_falls_back_to_default_when_no_declared_value(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([]);

        $estimate = $calculator->cycleLength($cycles);

        $this->assertSame(EstimateSource::Default, $estimate->source);
        $this->assertSame(28.0, $estimate->days);
    }

    public function test_cycle_length_window_caps_at_six_measurements_dropping_the_oldest(): void
    {
        $calculator = new CycleCalculator();

        // 8 cycle starts spaced 30 days apart -> 7 diffs, capped to the 6 most recent.
        $cycles = new Collection([
            $this->cycle('2026-01-01'),
            $this->cycle('2026-01-31'),
            $this->cycle('2026-03-02'),
            $this->cycle('2026-04-01'),
            $this->cycle('2026-05-01'),
            $this->cycle('2026-05-31'),
            $this->cycle('2026-06-30'),
            $this->cycle('2026-07-30'),
        ]);

        $estimate = $calculator->cycleLength($cycles);

        $this->assertSame(6, $estimate->sampleSize);
        $this->assertSame(30.0, $estimate->days);
    }

    public function test_cycle_length_variance_on_known_series(): void
    {
        $calculator = new CycleCalculator();

        $cycles = new Collection([
            $this->cycle('2026-01-01'),
            $this->cycle('2026-01-28'), // +27
            $this->cycle('2026-02-25'), // +28
            $this->cycle('2026-03-26'), // +29
        ]);

        $estimate = $calculator->cycleLength($cycles);

        $this->assertSame(28.0, $estimate->days);
        $this->assertSame(1.0, $estimate->stdDev);
    }

    public function test_cycle_without_menses_ended_on_is_ignored_by_menses_length_but_kept_by_cycle_length(): void
    {
        $calculator = new CycleCalculator();
        $cycles = new Collection([
            $this->cycle('2026-01-01', null),
            $this->cycle('2026-01-29', '2026-02-02'),
        ]);

        $cycleLength = $calculator->cycleLength($cycles);
        $mensesLength = $calculator->mensesLength($cycles);

        $this->assertSame(EstimateSource::Measured, $cycleLength->source);
        $this->assertSame(28.0, $cycleLength->days);
        $this->assertSame(1, $mensesLength->sampleSize);
        $this->assertSame(5.0, $mensesLength->days);
    }
}
