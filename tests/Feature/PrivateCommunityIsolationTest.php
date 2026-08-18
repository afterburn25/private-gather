<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CommunityChatMessage;
use App\Models\CommunityPost;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PrivateCommunityIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wall_is_members_only_and_bound_to_current_hosted_site(): void
    {
        [$alpha, $alphaHost] = $this->tenant('alpha-community.test');
        [$beta, $betaHost] = $this->tenant('beta-community.test');
        $alice = $this->user('alice@example.test');
        $bob = $this->user('bob@example.test');
        $alpha->users()->attach($alice->id, ['role' => 'member', 'status' => 'active']);
        $beta->users()->attach($bob->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($alice)
            ->post('http://'.$alphaHost.'/community/posts', ['body' => 'Alpha members only'])
            ->assertRedirect();

        $this->assertDatabaseHas('community_posts', [
            'tenant_id' => $alpha->id,
            'user_id' => $alice->id,
            'body' => 'Alpha members only',
        ]);

        $this->actingAs($alice)
            ->get('http://'.$betaHost.'/community')
            ->assertForbidden();

        $this->actingAs($bob)
            ->get('http://'.$betaHost.'/community')
            ->assertOk()
            ->assertDontSee('Alpha members only');
    }

    public function test_direct_messages_can_only_target_active_members_of_same_site(): void
    {
        [$alpha, $alphaHost] = $this->tenant('alpha-messages.test');
        [$beta] = $this->tenant('beta-messages.test');
        $alice = $this->user('alice-messages@example.test');
        $amy = $this->user('amy-messages@example.test');
        $bob = $this->user('bob-messages@example.test');
        $alpha->users()->attach($alice->id, ['role' => 'member', 'status' => 'active']);
        $alpha->users()->attach($amy->id, ['role' => 'member', 'status' => 'active']);
        $beta->users()->attach($bob->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($alice)
            ->post('http://'.$alphaHost.'/messages/start', [
                'recipient_ids' => [$bob->id],
                'body' => 'Cross-site attempt',
            ])->assertStatus(422);

        $this->assertDatabaseMissing('messages', ['body' => 'Cross-site attempt']);

        $this->actingAs($alice)
            ->post('http://'.$alphaHost.'/messages/start', [
                'recipient_ids' => [$amy->id],
                'body' => 'Same-site hello',
            ])->assertRedirect();

        $conversation = Conversation::query()->where('tenant_id', $alpha->id)->firstOrFail();
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => 'Same-site hello',
        ]);
    }

    public function test_conversation_cannot_be_replayed_through_another_tenant_host(): void
    {
        [$alpha, $alphaHost] = $this->tenant('alpha-thread.test');
        [$beta, $betaHost] = $this->tenant('beta-thread.test');
        $alice = $this->user('alice-thread@example.test');
        $amy = $this->user('amy-thread@example.test');
        $alpha->users()->attach($alice->id, ['role' => 'member', 'status' => 'active']);
        $alpha->users()->attach($amy->id, ['role' => 'member', 'status' => 'active']);
        $beta->users()->attach($alice->id, ['role' => 'member', 'status' => 'active']);

        $conversation = Conversation::create(['tenant_id' => $alpha->id, 'type' => 'direct']);
        $conversation->participants()->attach([$alice->id, $amy->id]);
        $conversation->messages()->create(['user_id' => $amy->id, 'body' => 'Alpha secret', 'status' => 'sent']);

        $this->actingAs($alice)
            ->get('http://'.$alphaHost.'/messages/'.$conversation->id)
            ->assertOk()
            ->assertSee('Alpha secret');

        $this->actingAs($alice)
            ->get('http://'.$betaHost.'/messages/'.$conversation->id)
            ->assertNotFound();
    }

    public function test_live_chat_polling_is_tenant_scoped(): void
    {
        [$alpha, $alphaHost] = $this->tenant('alpha-chat.test');
        [$beta, $betaHost] = $this->tenant('beta-chat.test');
        $alice = $this->user('alice-chat@example.test');
        $bob = $this->user('bob-chat@example.test');
        $alpha->users()->attach($alice->id, ['role' => 'member', 'status' => 'active']);
        $beta->users()->attach($bob->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($alice)
            ->postJson('http://'.$alphaHost.'/community/chat', ['body' => 'Alpha live message'])
            ->assertCreated();

        $this->actingAs($bob)
            ->getJson('http://'.$betaHost.'/community/chat/poll?after=0')
            ->assertOk()
            ->assertJsonMissing(['body' => 'Alpha live message']);

        $this->assertDatabaseHas('community_chat_messages', [
            'tenant_id' => $alpha->id,
            'user_id' => $alice->id,
            'body' => 'Alpha live message',
        ]);
    }

    private function tenant(string $domain): array
    {
        $tenant = Tenant::create([
            'name' => ucfirst(strtok($domain, '.')),
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

    private function user(string $email): User
    {
        return User::create([
            'name' => 'Community Member',
            'display_name' => 'Community Member',
            'email' => $email,
            'password' => 'Password1234',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
    }
}
