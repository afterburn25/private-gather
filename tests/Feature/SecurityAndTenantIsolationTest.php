<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SecurityAndTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_request_does_not_disclose_account_existence(): void
    {
        Notification::fake();
        $this->createUser('member@example.test');

        $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'member@example.test']);
        $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'missing@example.test']);

        $known->assertRedirect('/forgot-password');
        $unknown->assertRedirect('/forgot-password');
        $known->assertSessionHas('status', 'If an account exists for that email address, a password reset link has been sent.');
        $unknown->assertSessionHas('status', 'If an account exists for that email address, a password reset link has been sent.');
    }

    public function test_suspended_account_is_logged_out_even_with_an_existing_session(): void
    {
        $user = $this->createUser('suspended@example.test');
        $user->update(['status' => 'suspended']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_password_reset_invalidates_existing_database_sessions(): void
    {
        $user = $this->createUser('reset-sessions@example.test');
        $token = Password::broker()->createToken($user);
        DB::table('sessions')->insert([
            'id' => 'existing-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_platform_admin_cannot_remove_own_admin_access(): void
    {
        $admin = $this->createUser('admin@example.test');
        $admin->update(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->patch('/admin/users/'.$admin->id, [
                'status' => 'active',
                'is_platform_admin' => '0',
            ])
            ->assertStatus(422);

        $this->assertTrue($admin->fresh()->is_platform_admin);
    }

    public function test_platform_admin_login_normalizes_email_case(): void
    {
        $admin = $this->createUser('admin-case@example.test');
        $admin->update(['is_platform_admin' => true]);

        $this->post('/admin/login', [
            'email' => 'ADMIN-CASE@EXAMPLE.TEST',
            'password' => 'Password123',
        ])->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_two_factor_setup_requires_current_password(): void
    {
        $user = $this->createUser('2fa@example.test');

        $this->actingAs($user)
            ->from('/security')
            ->post('/security/two-factor', [])
            ->assertRedirect('/security')
            ->assertSessionHasErrors('password');
    }

    public function test_primary_login_moves_two_factor_user_into_guest_challenge_state(): void
    {
        $user = $this->createUser('challenge@example.test');
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $this->post('/login', [
            'email' => ' CHALLENGE@EXAMPLE.TEST ',
            'password' => 'Password123',
        ])->assertRedirect(route('two-factor.challenge'))
            ->assertSessionHas('auth.2fa_user', $user->id);

        $this->assertGuest();
    }

    public function test_manager_cannot_invite_or_remove_tenant_staff(): void
    {
        [$tenant, $domain] = $this->createTenant('staff.test');
        $manager = $this->createUser('manager@example.test');
        $staff = $this->createUser('staff@example.test');
        $tenant->users()->attach($manager->id, ['role' => 'manager', 'status' => 'active']);
        $tenant->users()->attach($staff->id, ['role' => 'staff', 'status' => 'active']);

        $this->actingAs($manager)
            ->post('http://'.$domain.'/manage/staff/invite', ['email' => 'new-admin@example.test', 'role' => 'admin'])
            ->assertForbidden();

        $this->actingAs($manager)
            ->delete('http://'.$domain.'/manage/staff/'.$staff->id)
            ->assertForbidden();

        $this->assertDatabaseMissing('tenant_invitations', ['tenant_id' => $tenant->id, 'email' => 'new-admin@example.test']);
        $this->assertDatabaseHas('tenant_users', ['tenant_id' => $tenant->id, 'user_id' => $staff->id, 'role' => 'staff']);
    }

    public function test_owner_can_invite_staff(): void
    {
        [$tenant, $domain] = $this->createTenant('owner-staff.test');
        $owner = $this->createUser('owner@example.test');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($owner)
            ->from('http://'.$domain.'/manage/staff')
            ->post('http://'.$domain.'/manage/staff/invite', ['email' => 'ADMIN@EXAMPLE.TEST', 'role' => 'admin'])
            ->assertRedirect('http://'.$domain.'/manage/staff');

        $this->assertDatabaseHas('tenant_invitations', [
            'tenant_id' => $tenant->id,
            'email' => 'admin@example.test',
            'role' => 'admin',
        ]);
    }

    public function test_block_applies_to_an_existing_direct_conversation(): void
    {
        [$tenant, $domain] = $this->createTenant('blocked-messages.test');
        $sender = $this->createUser('sender@example.test');
        $recipient = $this->createUser('recipient@example.test');
        $tenant->users()->attach($sender->id, ['role' => 'member', 'status' => 'active']);
        $tenant->users()->attach($recipient->id, ['role' => 'member', 'status' => 'active']);

        $conversation = Conversation::create(['tenant_id' => $tenant->id, 'type' => 'direct']);
        $conversation->participants()->attach([$sender->id, $recipient->id], ['last_read_at' => now()]);
        $conversation->messages()->create(['user_id' => $sender->id, 'body' => 'before block', 'status' => 'sent']);
        DB::table('user_blocks')->insert([
            'user_id' => $recipient->id,
            'blocked_user_id' => $sender->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($sender)
            ->post('http://'.$domain.'/messages/'.$conversation->id, ['body' => 'must not be delivered'])
            ->assertForbidden();

        $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id, 'body' => 'must not be delivered']);
    }

    public function test_report_rejects_nonexistent_target(): void
    {
        $member = $this->createUser('reporter@example.test');

        $this->actingAs($member)
            ->post('/reports', [
                'reportable_type' => 'event',
                'reportable_id' => 999999,
                'category' => 'safety',
                'details' => 'This target does not exist.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_member_cannot_report_message_from_conversation_they_do_not_participate_in(): void
    {
        $sender = $this->createUser('message-sender@example.test');
        $recipient = $this->createUser('message-recipient@example.test');
        $outsider = $this->createUser('message-outsider@example.test');
        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$sender->id, $recipient->id]);
        $message = $conversation->messages()->create([
            'user_id' => $sender->id,
            'body' => 'private conversation message',
            'status' => 'sent',
        ]);

        $this->actingAs($outsider)
            ->post('/reports', [
                'reportable_type' => 'message',
                'reportable_id' => $message->id,
                'category' => 'harassment',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_central_event_report_uses_reported_events_tenant(): void
    {
        [$reportedTenant] = $this->createTenant('reported-event.test');
        $event = $this->createEvent($reportedTenant, 'Reported Event', 'public');
        $member = $this->createUser('tenant-report@example.test');

        $this->actingAs($member)
            ->from('/events')
            ->post('/reports', [
                'reportable_type' => 'event',
                'reportable_id' => $event->id,
                'category' => 'privacy',
                'details' => 'Central moderation should route to the reported event tenant.',
            ])
            ->assertRedirect('/events');

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $member->id,
            'tenant_id' => $reportedTenant->id,
            'reportable_type' => 'event',
            'reportable_id' => $event->id,
        ]);
    }

    public function test_anonymous_tenant_listing_hides_members_and_unlisted_events(): void
    {
        [$tenant, $domain] = $this->createTenant('visibility.test');
        $this->createEvent($tenant, 'Public Event', 'public');
        $this->createEvent($tenant, 'Members Event', 'members');
        $this->createEvent($tenant, 'Secret Link Event', 'unlisted');

        $this->get('http://'.$domain.'/events')
            ->assertOk()
            ->assertSee('Public Event')
            ->assertDontSee('Members Event')
            ->assertDontSee('Secret Link Event');
    }

    public function test_authenticated_member_listing_includes_members_but_not_unlisted_events(): void
    {
        [$tenant, $domain] = $this->createTenant('member-visibility.test');
        $member = $this->createUser('visible-member@example.test');
        $this->createEvent($tenant, 'Public Event', 'public');
        $this->createEvent($tenant, 'Members Event', 'members');
        $this->createEvent($tenant, 'Secret Link Event', 'unlisted');

        $this->actingAs($member)
            ->get('http://'.$domain.'/events')
            ->assertOk()
            ->assertSee('Public Event')
            ->assertSee('Members Event')
            ->assertDontSee('Secret Link Event');
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Test Member',
            'display_name' => 'Test Member',
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

    private function createTenant(string $domain): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Organization',
            'slug' => str_replace('.', '-', $domain),
            'type' => 'club',
            'status' => 'active',
            'plan' => 'starter',
            'settings' => ['marketplace_enabled' => true],
        ]);
        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'type' => TenantDomain::TYPE_CUSTOM_DOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => 'active',
            'dns_status' => 'active',
        ]);

        return [$tenant, $domain];
    }

    private function createEvent(Tenant $tenant, string $title, string $visibility): Event
    {
        return Event::create([
            'tenant_id' => $tenant->id,
            'title' => $title,
            'slug' => strtolower(str_replace(' ', '-', $title)),
            'visibility' => $visibility,
            'rsvp_mode' => 'instant',
            'status' => 'published',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Chicago',
            'waitlist_enabled' => true,
            'exact_address_visibility' => 'approved_attendees',
        ]);
    }
}
