<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CycleAnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_summarizes_menses_and_cycle_length_with_history_and_forecast(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-01', 'menses_ended_on' => '2026-01-04']);
        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-29', 'menses_ended_on' => '2026-02-01']);
        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-02-26', 'menses_ended_on' => '2026-03-02']);

        $response = $this->getJson('/api/cycle-analysis');

        $response->assertOk();

        // Menses durations: 4, 4, 5 -> variation 1, last (most recent) = 5.
        $response->assertJsonPath('menses.last_duration', 5);
        $response->assertJsonPath('menses.variation_days', 1);
        $response->assertJsonPath('menses.sample_size', 3);

        // Cycle lengths: Jan1->Jan29 = 28, Jan29->Feb26 = 28 -> variation 0, last = 28.
        $response->assertJsonPath('cycle.last_duration', 28);
        $response->assertJsonPath('cycle.variation_days', 0);
        $response->assertJsonPath('cycle.regularity', 'regular'); // amplitude 0 <= permissive threshold (no birth_date)

        $history = $response->json('history');
        $this->assertCount(3, $history);
        // Most recent first.
        $this->assertSame('2026-02-26', $history[0]['started_on']);
        $this->assertSame(5, $history[0]['menses_duration']);
        $this->assertNull($history[0]['cycle_duration']); // no next cycle logged yet
        $this->assertSame('2026-01-01', $history[2]['started_on']);
        $this->assertSame(28, $history[2]['cycle_duration']);

        $forecast = $response->json('forecast');
        $this->assertCount(3, $forecast);
        $this->assertSame('2026-03-26', $forecast[0]['start']); // Feb26 + 28
        $this->assertSame('2026-04-23', $forecast[1]['start']); // + another 28
    }

    public function test_sample_size_reflects_real_history_even_when_an_override_is_active(): void
    {
        Sanctum::actingAs($user = User::factory()->create(['cycle_length_override' => 45]));

        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-01']);
        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-29']);

        $response = $this->getJson('/api/cycle-analysis');

        // The override drives the forecast (see below) but must not zero out
        // sample_size — that would misrepresent an established user's history.
        $response->assertOk()->assertJsonPath('cycle.sample_size', 1);
        $forecast = $response->json('forecast');
        $this->assertSame('2026-03-15', $forecast[0]['start']); // Jan29 + 45, overridden
    }

    public function test_it_returns_empty_history_and_forecast_without_any_cycle(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/cycle-analysis');

        $response->assertOk();
        $this->assertSame([], $response->json('history'));
        $this->assertSame([], $response->json('forecast'));
        $this->assertNull($response->json('menses.last_duration'));
        $this->assertNull($response->json('cycle.last_duration'));
    }
}
