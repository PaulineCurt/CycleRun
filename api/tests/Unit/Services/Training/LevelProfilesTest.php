<?php

namespace Tests\Unit\Services\Training;

use App\Enums\Level;
use App\Services\Training\LevelProfiles;
use PHPUnit\Framework\TestCase;

class LevelProfilesTest extends TestCase
{
    public function test_every_level_resolves_to_a_profile(): void
    {
        foreach (Level::cases() as $level) {
            $this->assertNotNull(LevelProfiles::for($level));
        }
    }

    public function test_starting_volume_and_caps_grow_strictly_with_the_level(): void
    {
        $d = LevelProfiles::for(Level::Debutante);
        $i = LevelProfiles::for(Level::Intermediaire);
        $c = LevelProfiles::for(Level::Confirmee);

        $this->assertLessThan($i->minutesPerSessionStart, $d->minutesPerSessionStart);
        $this->assertLessThan($c->minutesPerSessionStart, $i->minutesPerSessionStart);

        $this->assertLessThan($i->sessionMinutesCap, $d->sessionMinutesCap);
        $this->assertLessThan($c->sessionMinutesCap, $i->sessionMinutesCap);
    }

    public function test_only_the_debutante_starts_run_walk(): void
    {
        $this->assertTrue(LevelProfiles::for(Level::Debutante)->startsRunWalk);
        $this->assertFalse(LevelProfiles::for(Level::Intermediaire)->startsRunWalk);
        $this->assertFalse(LevelProfiles::for(Level::Confirmee)->startsRunWalk);
    }

    public function test_the_improvement_ceiling_shrinks_as_the_runner_advances(): void
    {
        // a débutante has more headroom to improve over one plan than a confirmée
        $this->assertGreaterThan(
            LevelProfiles::for(Level::Intermediaire)->maxImprovement,
            LevelProfiles::for(Level::Debutante)->maxImprovement,
        );
        $this->assertGreaterThan(
            LevelProfiles::for(Level::Confirmee)->maxImprovement,
            LevelProfiles::for(Level::Intermediaire)->maxImprovement,
        );
    }
}
