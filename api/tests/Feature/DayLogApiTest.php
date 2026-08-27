<?php

namespace Tests\Feature;

use App\Models\DayLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DayLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_a_day_log(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/day-logs', [
            'date' => '2026-01-01',
            'flow' => 'medium',
            'pain' => 1,
            'energy' => 3,
            'symptoms' => ['bloating' => 2, 'headache' => 0],
        ])->assertCreated()->assertJsonPath('flow', 'medium')->assertJsonPath('symptoms.bloating', 2);
    }

    public function test_a_symptom_intensity_outside_the_0_to_3_range_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/day-logs', [
            'date' => '2026-01-01',
            'symptoms' => ['bloating' => 4],
        ])->assertUnprocessable();
    }

    public function test_a_second_log_on_the_same_date_is_rejected(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        DayLog::create(['user_id' => $user->id, 'date' => '2026-01-01']);

        $this->postJson('/api/day-logs', ['date' => '2026-01-01'])
            ->assertUnprocessable();
    }

    public function test_the_same_date_is_allowed_for_a_different_user(): void
    {
        $other = User::factory()->create();
        DayLog::create(['user_id' => $other->id, 'date' => '2026-01-01']);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/day-logs', ['date' => '2026-01-01'])->assertCreated();
    }

    public function test_a_user_cannot_delete_another_users_day_log(): void
    {
        $owner = User::factory()->create();
        $log = DayLog::create(['user_id' => $owner->id, 'date' => '2026-01-01']);

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/day-logs/{$log->id}")->assertNotFound();
    }

    public function test_updating_symptoms_merges_instead_of_replacing(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $log = DayLog::create(['user_id' => $user->id, 'date' => '2026-01-01', 'symptoms' => ['headache' => 2]]);

        // Simulates a second tap arriving after the first, each sending only the one
        // key it changed — the previously-saved key must survive, not get wiped.
        $this->patchJson("/api/day-logs/{$log->id}", ['symptoms' => ['acne' => 1]])
            ->assertOk()
            ->assertJsonPath('symptoms.headache', 2)
            ->assertJsonPath('symptoms.acne', 1);
    }

    public function test_the_index_can_be_filtered_by_date(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        DayLog::create(['user_id' => $user->id, 'date' => '2026-01-01']);
        DayLog::create(['user_id' => $user->id, 'date' => '2026-01-02']);

        $response = $this->getJson('/api/day-logs?date=2026-01-02')->assertOk();

        $response->assertJsonCount(1);
        $response->assertJsonPath('0.date', '2026-01-02');
    }
}
