<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_started_on_is_unique_per_user(): void
    {
        $user = User::factory()->create();
        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-01']);

        $this->expectException(QueryException::class);

        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-01']);
    }

    public function test_same_started_on_is_allowed_for_different_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Cycle::create(['user_id' => $userA->id, 'started_on' => '2026-01-01']);
        $cycle = Cycle::create(['user_id' => $userB->id, 'started_on' => '2026-01-01']);

        $this->assertTrue($cycle->exists);
    }

    public function test_deleting_user_cascades_to_cycles(): void
    {
        $user = User::factory()->create();
        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-01']);

        $user->delete();

        $this->assertDatabaseCount('cycles', 0);
    }

    public function test_dates_are_cast_to_carbon(): void
    {
        $user = User::factory()->create();
        $cycle = Cycle::create([
            'user_id' => $user->id,
            'started_on' => '2026-01-01',
            'menses_ended_on' => '2026-01-05',
        ]);

        $cycle->refresh();

        $this->assertSame('2026-01-01', $cycle->started_on->toDateString());
        $this->assertSame('2026-01-05', $cycle->menses_ended_on->toDateString());
    }

    public function test_menses_ended_on_is_nullable(): void
    {
        $user = User::factory()->create();
        $cycle = Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-01']);

        $cycle->refresh();

        $this->assertNull($cycle->menses_ended_on);
    }
}
