<?php

namespace Tests\Feature;

use App\Models\ExclusionPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExclusionPeriodApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_and_list_their_exclusion_periods(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $this->postJson('/api/exclusion-periods', [
            'type' => 'pregnancy',
            'started_on' => '2026-01-01',
        ])->assertCreated();

        $this->getJson('/api/exclusion-periods')->assertOk()->assertJsonCount(1);
    }

    public function test_a_user_cannot_update_another_users_exclusion_period(): void
    {
        $owner = User::factory()->create();
        $period = ExclusionPeriod::create([
            'user_id' => $owner->id,
            'type' => 'pregnancy',
            'started_on' => '2026-01-01',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/exclusion-periods/{$period->id}", ['ended_on' => '2026-02-01'])
            ->assertNotFound();
    }

    public function test_updating_ended_on_before_started_on_is_rejected(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $period = ExclusionPeriod::create([
            'user_id' => $user->id,
            'type' => 'pregnancy',
            'started_on' => '2026-01-10',
        ]);

        $this->patchJson("/api/exclusion-periods/{$period->id}", ['ended_on' => '2026-01-05'])
            ->assertUnprocessable();
    }

    public function test_updating_ended_on_against_a_newly_patched_started_on_is_validated_together(): void
    {
        // started_on itself is changing in the same request — the closure rule must
        // compare against the NEW value, not the model's stale one.
        Sanctum::actingAs($user = User::factory()->create());
        $period = ExclusionPeriod::create([
            'user_id' => $user->id,
            'type' => 'pregnancy',
            'started_on' => '2026-01-01',
        ]);

        $this->patchJson("/api/exclusion-periods/{$period->id}", [
            'started_on' => '2026-02-10',
            'ended_on' => '2026-02-05',
        ])->assertUnprocessable();
    }
}
