<?php

namespace Tests\Unit\Services\Training;

use App\Services\Training\LevelNormalizer;
use App\Services\Training\LevelSource;
use PHPUnit\Framework\TestCase;

class LevelNormalizerTest extends TestCase
{
    private LevelNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new LevelNormalizer();
    }

    public function test_field_test_with_explicit_vma(): void
    {
        $level = $this->normalizer->normalize(['kind' => 'field_test', 'vma_kmh' => 14.0]);

        $this->assertSame(14.0, $level->vmaKmh);
        $this->assertSame(LevelSource::FieldTest, $level->source);
        $this->assertNull($level->vdot);
        $this->assertNull($level->referenceDistanceM);
    }

    public function test_field_test_from_half_cooper_distance(): void
    {
        // 1400 m covered in 6 min => VMA 14 km/h
        $level = $this->normalizer->normalize(['kind' => 'field_test', 'half_cooper_m' => 1400]);

        $this->assertSame(14.0, $level->vmaKmh);
    }

    public function test_paces_follow_the_vma_percentage_formula(): void
    {
        $level = $this->normalizer->normalize(['kind' => 'field_test', 'vma_kmh' => 14.0]);

        // easy zone 65–75 %: 3600/(14·0.75)=343 .. 3600/(14·0.65)=396
        $this->assertSame([343, 396], $level->paces['easy']);
        // the doc's worked example (70 % => ~6:07/km => 367 s) sits inside that range
        $this->assertGreaterThanOrEqual($level->paces['easy'][0], 367);
        $this->assertLessThanOrEqual($level->paces['easy'][1], 367);
        // interval zone 95–105 %
        $this->assertSame([245, 271], $level->paces['interval']);
    }

    public function test_all_five_zones_are_present_and_ordered_fast_to_slow(): void
    {
        $level = $this->normalizer->normalize(['kind' => 'field_test', 'vma_kmh' => 14.0]);

        foreach (['recovery', 'easy', 'tempo', 'threshold', 'interval'] as $zone) {
            $this->assertArrayHasKey($zone, $level->paces);
            $this->assertLessThanOrEqual($level->paces[$zone][1], $level->paces[$zone][0]);
        }

        // faster zone => fewer seconds per km
        $this->assertLessThan($level->paces['easy'][0], $level->paces['interval'][0]);
        $this->assertLessThan($level->paces['recovery'][0], $level->paces['easy'][0]);
    }

    public function test_race_result_derives_vdot_and_vma_and_keeps_the_reference(): void
    {
        $level = $this->normalizer->normalize([
            'kind' => 'race_result',
            'distance_m' => 5000,
            'time_s' => 1197,
        ]);

        $this->assertSame(LevelSource::RaceResult, $level->source);
        $this->assertEqualsWithDelta(50.0, $level->vdot, 0.5);
        $this->assertEqualsWithDelta(15.65, $level->vmaKmh, 0.2);
        $this->assertSame(5000, $level->referenceDistanceM);
        $this->assertSame(1197, $level->referenceTimeS);
        $this->assertArrayHasKey('threshold', $level->paces);
    }

    public function test_unknown_kind_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->normalizer->normalize(['kind' => 'guesstimate']);
    }

    public function test_field_test_without_usable_input_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->normalizer->normalize(['kind' => 'field_test']);
    }

    public function test_non_positive_vma_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->normalizer->normalize(['kind' => 'field_test', 'vma_kmh' => 0]);
    }

    public function test_is_deterministic(): void
    {
        $input = ['kind' => 'race_result', 'distance_m' => 5000, 'time_s' => 1350];

        $this->assertEquals(
            $this->normalizer->normalize($input),
            $this->normalizer->normalize($input),
        );
    }
}
