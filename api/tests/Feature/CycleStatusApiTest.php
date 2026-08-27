<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CycleStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_is_no_data_without_any_logged_cycle(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/cycle-status')->assertOk()->assertJsonPath('status', 'no_data');
    }

    public function test_status_resolves_a_phase_from_a_declared_cycle(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-01']);

        $response = $this->getJson('/api/cycle-status?date=2026-01-03');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('phase', 'menstrual')
            ->assertJsonPath('cycle_day', 3)
            ->assertJsonPath('low_confidence', true);
    }

    public function test_a_manual_cycle_length_override_wins_over_the_declared_value(): void
    {
        Sanctum::actingAs($user = User::factory()->create([
            'typical_cycle_length' => 28,
            'cycle_length_override' => 35,
        ]));
        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-01']);

        $response = $this->getJson('/api/cycle-status?date=2026-01-03');

        $response->assertOk()->assertJsonPath('predicted_next_start', '2026-02-05');
    }
}
