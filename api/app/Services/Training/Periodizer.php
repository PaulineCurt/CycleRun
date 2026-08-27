<?php

namespace App\Services\Training;

/**
 * Step 4 of the pipeline (§5): buildPeriodization(weeks, days_per_week) -> weekly
 * blocks with a volume target and an easy/hard split.
 *
 * Encodes §4.5: base → development → taper mesocycles, +10 %/week volume ceiling,
 * a deload every 4th week (−35 %), and a 1–2 week taper. All constants are
 * provisional and are the priority item for coach (FFA) review.
 */
final class Periodizer
{
    private const MINUTES_PER_SESSION_START = 25;   // week-0 session length. TODO(expert).
    private const WEEKLY_PROGRESSION = 1.10;         // §4.5 ceiling.
    private const DELOAD_EVERY = 4;                  // §4.5: every 3–4 weeks.
    private const DELOAD_FACTOR = 0.65;              // §4.5: −30..−40 %.
    private const SESSION_MINUTES_CAP = 75;          // avg minutes/session hard cap. TODO(expert).
    private const BASE_SHARE = 0.45;                 // fraction of working weeks spent in base.
    private const BASE_HARD_RATIO = 0.05;            // strides only during base.
    private const DEV_HARD_RATIO_START = 0.12;
    private const DEV_HARD_RATIO_END = 0.20;         // the "20" of 80/20 (§4.4).
    private const TAPER_HARD_RATIO = 0.15;
    private const TAPER_VOLUME_START = 0.60;         // fraction of peak on first taper week.
    private const TAPER_VOLUME_STEP = 0.15;          // shed per further taper week.

    /**
     * @return list<WeekBlock>
     */
    public function build(int $weeks, int $daysPerWeek, Track $track): array
    {
        if ($weeks < 1 || $daysPerWeek < 1) {
            throw new \InvalidArgumentException('weeks and days_per_week must be positive');
        }

        $taperWeeks = $this->taperWeeks($weeks);
        $workingWeeks = $weeks - $taperWeeks;
        $baseWeeks = max(1, (int) ceil($workingWeeks * self::BASE_SHARE));
        $baseWeeks = min($baseWeeks, $workingWeeks);
        $developmentWeeks = $workingWeeks - $baseWeeks;

        $startWeekly = $daysPerWeek * self::MINUTES_PER_SESSION_START;
        $volumeCap = $daysPerWeek * self::SESSION_MINUTES_CAP;

        $blocks = [];
        $progressionStep = 0;
        $lastVolume = $startWeekly;
        $peakVolume = $startWeekly;

        for ($i = 0; $i < $workingWeeks; $i++) {
            $isDeload = $this->isDeloadWeek($i, $workingWeeks);
            $mesocycle = $i < $baseWeeks ? Mesocycle::Base : Mesocycle::Development;

            if ($isDeload) {
                $volume = (int) round($lastVolume * self::DELOAD_FACTOR);
            } else {
                $volume = (int) round(min($startWeekly * self::WEEKLY_PROGRESSION ** $progressionStep, $volumeCap));
                $progressionStep++;
                $lastVolume = $volume;
                $peakVolume = max($peakVolume, $volume);
            }

            $hardRatio = $this->hardRatio($mesocycle, $i, $baseWeeks, $developmentWeeks, $isDeload);
            $blocks[] = $this->makeBlock($i, $mesocycle, $isDeload, $volume, $hardRatio);
        }

        for ($k = 0; $k < $taperWeeks; $k++) {
            $factor = max(0.30, self::TAPER_VOLUME_START - self::TAPER_VOLUME_STEP * $k);
            $volume = (int) round($peakVolume * $factor);
            $blocks[] = $this->makeBlock($workingWeeks + $k, Mesocycle::Taper, false, $volume, self::TAPER_HARD_RATIO);
        }

        return $blocks;
    }

    private function taperWeeks(int $weeks): int
    {
        return match (true) {
            $weeks >= 10 => 2,
            $weeks >= 5 => 1,
            default => 0,
        };
    }

    private function isDeloadWeek(int $weekIndex, int $workingWeeks): bool
    {
        // Every 4th week, but never week 0 and never the last working week
        // (that one flows into the taper).
        return ($weekIndex + 1) % self::DELOAD_EVERY === 0
            && $weekIndex !== 0
            && $weekIndex !== $workingWeeks - 1;
    }

    private function hardRatio(
        Mesocycle $mesocycle,
        int $weekIndex,
        int $baseWeeks,
        int $developmentWeeks,
        bool $isDeload,
    ): float {
        $ratio = match ($mesocycle) {
            Mesocycle::Base => self::BASE_HARD_RATIO,
            Mesocycle::Development => $developmentWeeks <= 1
                ? self::DEV_HARD_RATIO_END
                : self::DEV_HARD_RATIO_START
                    + ($this->clamp01(($weekIndex - $baseWeeks) / ($developmentWeeks - 1)))
                    * (self::DEV_HARD_RATIO_END - self::DEV_HARD_RATIO_START),
            Mesocycle::Taper => self::TAPER_HARD_RATIO,
        };

        return $isDeload ? $ratio / 2 : $ratio;
    }

    private function makeBlock(int $weekIndex, Mesocycle $mesocycle, bool $isDeload, int $volume, float $hardRatio): WeekBlock
    {
        $hard = (int) round($volume * $hardRatio);

        return new WeekBlock($weekIndex, $mesocycle, $isDeload, $volume, $hard, $volume - $hard);
    }

    private function clamp01(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
