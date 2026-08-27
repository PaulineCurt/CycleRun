<?php

namespace Tests\Feature;

use App\Enums\Flow;
use App\Models\DayLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DayLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_is_unique_per_user(): void
    {
        $user = User::factory()->create();
        DayLog::create(['user_id' => $user->id, 'date' => '2026-01-01']);

        $this->expectException(QueryException::class);

        DayLog::create(['user_id' => $user->id, 'date' => '2026-01-01']);
    }

    public function test_same_date_is_allowed_for_different_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        DayLog::create(['user_id' => $userA->id, 'date' => '2026-01-01']);
        $log = DayLog::create(['user_id' => $userB->id, 'date' => '2026-01-01']);

        $this->assertTrue($log->exists);
    }

    public function test_deleting_user_cascades_to_day_logs(): void
    {
        $user = User::factory()->create();
        DayLog::create(['user_id' => $user->id, 'date' => '2026-01-01']);

        $user->delete();

        $this->assertDatabaseCount('day_logs', 0);
    }

    public function test_flow_is_cast_to_enum(): void
    {
        $user = User::factory()->create();
        $log = DayLog::create([
            'user_id' => $user->id,
            'date' => '2026-01-01',
            'flow' => Flow::Medium,
        ]);

        $log->refresh();

        $this->assertSame(Flow::Medium, $log->flow);
    }

    public function test_symptoms_is_cast_to_array(): void
    {
        $user = User::factory()->create();
        $log = DayLog::create([
            'user_id' => $user->id,
            'date' => '2026-01-01',
            'symptoms' => ['bloating', 'headache'],
        ]);

        $log->refresh();

        $this->assertSame(['bloating', 'headache'], $log->symptoms);
    }

    public function test_pain_zero_is_distinct_from_null(): void
    {
        $user = User::factory()->create();

        $withPain = DayLog::create(['user_id' => $user->id, 'date' => '2026-01-01', 'pain' => 0]);
        $withoutPain = DayLog::create(['user_id' => $user->id, 'date' => '2026-01-02', 'pain' => null]);

        $withPain->refresh();
        $withoutPain->refresh();

        $this->assertSame(0, $withPain->pain);
        $this->assertNull($withoutPain->pain);
    }

    public function test_date_is_cast_to_carbon(): void
    {
        $user = User::factory()->create();
        $log = DayLog::create(['user_id' => $user->id, 'date' => '2026-01-01']);

        $log->refresh();

        $this->assertSame('2026-01-01', $log->date->toDateString());
    }
}
