<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CycleCalendarApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_phase_per_day_for_the_requested_month(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $cycle = Cycle::create([
            'user_id' => $user->id,
            'started_on' => '2026-01-01',
            'menses_ended_on' => '2026-01-05',
        ]);

        $response = $this->getJson('/api/cycle-calendar?month=2026-01');

        $response->assertOk()->assertJsonPath('month', '2026-01');
        $days = $response->json('days');

        $this->assertCount(31, $days);
        $this->assertSame('2026-01-01', $days[0]['date']);
        $this->assertSame('menstrual', $days[0]['phase']);
        $this->assertSame($cycle->id, $days[0]['cycle_id']);
        $this->assertSame(1, $days[0]['cycle_day']);
        $this->assertSame(5, $days[4]['cycle_day']);
        $this->assertTrue($days[0]['is_cycle_start']);
        $this->assertFalse($days[0]['is_menses_end']);
        $this->assertTrue($days[4]['is_menses_end']);
        $this->assertFalse($days[1]['is_cycle_start']);
        $this->assertSame('2026-01-31', $days[30]['date']);

        // predictedNextStart = Jan 1 + 28 (default, only one cycle logged) = Jan 29: from
        // there on, nothing is actually logged yet, it's a projection of the next cycle.
        $this->assertFalse($days[27]['is_projected']); // 2026-01-28
        $this->assertTrue($days[28]['is_projected']); // 2026-01-29
        $this->assertSame('menstrual', $days[28]['phase']);
    }

    public function test_the_projection_keeps_repeating_beyond_the_first_projected_period(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        Cycle::create([
            'user_id' => $user->id,
            'started_on' => '2026-01-01',
            'menses_ended_on' => '2026-01-05',
        ]);

        // predictedNextStart Jan 29, projected menses end Feb 2 (mensesLength measured = 5).
        $response = $this->getJson('/api/cycle-calendar?month=2026-02');

        $response->assertOk();
        $days = $response->json('days');

        $this->assertSame('menstrual', $days[0]['phase']); // 2026-02-01
        $this->assertTrue($days[0]['is_projected']);
        $this->assertSame('menstrual', $days[1]['phase']); // 2026-02-02
        // Past the projected menstrual window, the day is classified against the *same*
        // simulated cycle (started Jan 29) rather than going blank.
        $this->assertSame('follicular', $days[2]['phase']); // 2026-02-03
        $this->assertTrue($days[2]['is_projected']);

        // Three 28-day cycles after Jan 29 lands the next simulated period on Apr 23-27 —
        // proving the projection repeats indefinitely instead of stopping after one cycle.
        $april = $this->getJson('/api/cycle-calendar?month=2026-04')->json('days');

        $this->assertSame('menstrual', $april[22]['phase']); // 2026-04-23
        $this->assertTrue($april[22]['is_projected']);
        $this->assertSame('menstrual', $april[26]['phase']); // 2026-04-27
        $this->assertNotNull($april[14]['phase']); // 2026-04-15, mid-cycle, still classified
    }

    public function test_days_before_any_logged_cycle_have_no_phase(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/cycle-calendar?month=2026-01');

        $response->assertOk();
        $this->assertNull($response->json('days.0.phase'));
        $this->assertNull($response->json('days.0.cycle_id'));
        $this->assertNull($response->json('days.0.cycle_day'));
        $this->assertFalse($response->json('days.0.predictable'));
    }

    public function test_it_defaults_to_the_current_month_without_a_query_param(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/cycle-calendar')->assertOk();
    }
}
