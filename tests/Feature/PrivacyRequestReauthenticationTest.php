<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyRequestReauthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_request_does_not_require_current_password(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->from('/security')
            ->post('/security/data-request', ['type' => 'export'])
            ->assertRedirect('/security')
            ->assertSessionHas('status', 'Privacy request submitted.');

        $this->assertDatabaseHas('data_requests', [
            'user_id' => $user->id,
            'type' => 'export',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'privacy.export.requested',
        ]);
    }

    public function test_delete_request_requires_correct_current_password(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->from('/security')
            ->post('/security/data-request', ['type' => 'delete'])
            ->assertRedirect('/security')
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('data_requests', [
            'user_id' => $user->id,
            'type' => 'delete',
        ]);

        $this->actingAs($user)
            ->from('/security')
            ->post('/security/data-request', [
                'type' => 'delete',
                'password' => 'WrongPassword123',
            ])
            ->assertRedirect('/security')
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('data_requests', [
            'user_id' => $user->id,
            'type' => 'delete',
        ]);

        $this->actingAs($user)
            ->from('/security')
            ->post('/security/data-request', [
                'type' => 'delete',
                'password' => 'Password123',
            ])
            ->assertRedirect('/security')
            ->assertSessionHas('status', 'Privacy request submitted.');

        $this->assertDatabaseHas('data_requests', [
            'user_id' => $user->id,
            'type' => 'delete',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'privacy.delete.requested',
        ]);
    }

    private function createUser(): User
    {
        return User::create([
            'name' => 'Privacy Test User',
            'display_name' => 'Privacy Test User',
            'email' => 'privacy-request@example.test',
            'password' => 'Password123',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
    }
}
