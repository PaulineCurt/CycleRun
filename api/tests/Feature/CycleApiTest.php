<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CycleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_cycles(): void
    {
        $this->getJson('/api/cycles')->assertUnauthorized();
    }

    public function test_a_user_can_create_and_list_their_cycles(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $this->postJson('/api/cycles', ['started_on' => '2026-01-01'])->assertCreated();

        $response = $this->getJson('/api/cycles');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_a_user_only_sees_their_own_cycles(): void
    {
        $owner = User::factory()->create();
        Cycle::create(['user_id' => $owner->id, 'started_on' => '2026-01-01']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/cycles')->assertOk()->assertJsonCount(0);
    }

    public function test_a_user_cannot_update_another_users_cycle(): void
    {
        $owner = User::factory()->create();
        $cycle = Cycle::create(['user_id' => $owner->id, 'started_on' => '2026-01-01']);

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/cycles/{$cycle->id}", ['menses_ended_on' => '2026-01-05'])
            ->assertNotFound();
    }

    public function test_a_user_can_update_their_own_cycle(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $cycle = Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-01']);

        $this->patchJson("/api/cycles/{$cycle->id}", ['menses_ended_on' => '2026-01-05'])
            ->assertOk()
            ->assertJsonPath('menses_ended_on', '2026-01-05');
    }

    public function test_updating_menses_ended_on_before_started_on_is_rejected(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $cycle = Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-10']);

        $this->patchJson("/api/cycles/{$cycle->id}", ['menses_ended_on' => '2026-01-05'])
            ->assertUnprocessable();
    }

    public function test_a_user_can_delete_their_own_cycle(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $cycle = Cycle::create(['user_id' => $user->id, 'started_on' => '2026-01-01']);

        $this->deleteJson("/api/cycles/{$cycle->id}")->assertNoContent();

        $this->assertDatabaseCount('cycles', 0);
    }
}
