<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_declared_fields_are_editable_when_nothing_is_measured_yet(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/profile');

        $response->assertOk()
            ->assertJsonPath('cycle_length_measured', false)
            ->assertJsonPath('menses_length_measured', false);
    }

    public function test_fields_flip_to_measured_once_real_history_exists(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-01', 'menses_ended_on' => '2026-01-05']);
        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-29']);

        $response = $this->getJson('/api/profile');

        $response->assertOk()
            ->assertJsonPath('cycle_length_measured', true)
            ->assertJsonPath('cycle_length_average', 28)
            ->assertJsonPath('menses_length_measured', true)
            ->assertJsonPath('menses_length_average', 5);
    }
}
