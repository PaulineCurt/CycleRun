<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'birth_date' => '1996-01-01',
            'terms_accepted' => true,
            'privacy_accepted' => true,
            'data_usage_accepted' => true,
        ], $overrides);
    }

    public function test_registration_records_birth_date_and_consent_timestamps(): void
    {
        $response = $this->postJson('/api/register', $this->payload());

        $response->assertCreated();

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('1996-01-01', $user->birth_date->toDateString());
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertNotNull($user->privacy_accepted_at);
        $this->assertNotNull($user->data_usage_accepted_at);
    }

    public function test_registration_is_rejected_without_all_three_consents(): void
    {
        $this->postJson('/api/register', $this->payload(['terms_accepted' => false]))
            ->assertUnprocessable();

        $this->postJson('/api/register', $this->payload(['privacy_accepted' => false]))
            ->assertUnprocessable();

        $this->postJson('/api/register', $this->payload(['data_usage_accepted' => false]))
            ->assertUnprocessable();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_birth_date_is_optional(): void
    {
        $payload = $this->payload();
        unset($payload['birth_date']);

        $this->postJson('/api/register', $payload)->assertCreated();

        $this->assertNull(User::where('email', 'jane@example.com')->firstOrFail()->birth_date);
    }
}
