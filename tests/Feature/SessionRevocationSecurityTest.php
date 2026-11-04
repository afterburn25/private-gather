<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionRevocationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_password_is_required_and_only_users_other_sessions_are_revoked(): void
    {
        $user = $this->createUser('session-owner@example.test');
        $other = $this->createUser('other-session@example.test');

        $this->insertSession('owner-session-one', $user->id);
        $this->insertSession('owner-session-two', $user->id);
        $this->insertSession('other-users-session', $other->id);

        $this->actingAs($user)
            ->from('/security')
            ->post('/security/sessions/revoke', ['password' => 'WrongPassword123'])
            ->assertRedirect('/security')
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('sessions', ['id' => 'owner-session-one', 'user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'owner-session-two', 'user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'other-users-session', 'user_id' => $other->id]);

        $this->actingAs($user)
            ->from('/security')
            ->post('/security/sessions/revoke', ['password' => 'Password123'])
            ->assertRedirect('/security')
            ->assertSessionHas('status', 'Signed out 2 other sessions.');

        $this->assertDatabaseMissing('sessions', ['id' => 'owner-session-one']);
        $this->assertDatabaseMissing('sessions', ['id' => 'owner-session-two']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-users-session', 'user_id' => $other->id]);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'sessions.revoked',
        ]);

        $this->actingAs($user)
            ->get('/security')
            ->assertOk()
            ->assertSee('Recent security activity')
            ->assertSee('Sessions Revoked');
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Session Security User',
            'display_name' => 'Session Security User',
            'email' => $email,
            'password' => 'Password123',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
    }

    private function insertSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test-agent',
            'payload' => 'test-payload',
            'last_activity' => time(),
        ]);
    }
}
