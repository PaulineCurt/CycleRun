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

    public function test_a_user_can_pick_a_predefined_avatar(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/profile', ['avatar' => 'moon'])
            ->assertOk()
            ->assertJsonPath('avatar', 'moon');
    }

    public function test_updating_the_profile_still_returns_the_measured_flags(): void
    {
        // A PATCH unrelated to cycle length (here: the avatar) must return the same
        // enriched shape as GET — a caller trusting this response as the new profile
        // state would otherwise see cycle_length_measured go missing and treat an
        // already-measured field as editable again.
        Sanctum::actingAs($user = User::factory()->create());
        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-01', 'menses_ended_on' => '2026-01-05']);
        Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-29']);

        $this->patchJson('/api/profile', ['avatar' => 'moon'])
            ->assertOk()
            ->assertJsonPath('cycle_length_measured', true)
            ->assertJsonPath('menses_length_measured', true);
    }

    public function test_a_cycle_length_override_outside_the_sane_bound_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // An override permanently wins over real measured data, so it gets a
        // tighter, domain-realistic bound than the pre-measurement declared field.
        $this->patchJson('/api/profile', ['cycle_length_override' => 1])->assertUnprocessable();
        $this->patchJson('/api/profile', ['cycle_length_override' => 35])->assertOk();
    }

    public function test_an_unknown_avatar_key_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/profile', ['avatar' => 'not-a-real-avatar'])
            ->assertUnprocessable();
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
