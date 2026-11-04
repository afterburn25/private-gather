<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SelfHostedEditionTestCommunity extends TestCase
{
    use RefreshDatabase;

    public function test_self_hosted_member_gets_wall_live_chat_and_private_messages_from_shared_core(): void
    {
        config([
            'app.url' => 'http://platform.test',
            'edition.name' => 'self_hosted',
            'edition.self_hosted.visibility' => 'private',
            'edition.self_hosted.registration' => 'approval',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Private House Community',
            'slug' => 'private-house-community',
            'type' => Tenant::TYPE_PRIVATE_HOST,
            'status' => 'active',
            'plan' => 'self_hosted',
            'settings' => ['marketplace_enabled' => false, 'self_hosted' => true],
        ]);
        config(['edition.self_hosted.tenant_id' => $tenant->id]);

        $owner = $this->user('owner-community@example.test');
        $member = $this->user('member-community@example.test');
        $tenant->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $tenant->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($member)
            ->get('http://platform.test/community')
            ->assertOk()
            ->assertSee('Private House Community');

        $this->actingAs($member)
            ->post('http://platform.test/community/posts', ['body' => 'Private self-hosted wall post'])
            ->assertRedirect();

        $this->actingAs($member)
            ->postJson('http://platform.test/community/chat', ['body' => 'Private live chat'])
            ->assertCreated();

        $this->actingAs($member)
            ->post('http://platform.test/messages/start', [
                'recipient_ids' => [$owner->id],
                'body' => 'Private direct message',
            ])->assertRedirect();

        $this->assertDatabaseHas('community_posts', ['tenant_id' => $tenant->id, 'body' => 'Private self-hosted wall post']);
        $this->assertDatabaseHas('community_chat_messages', ['tenant_id' => $tenant->id, 'body' => 'Private live chat']);
        $this->assertDatabaseHas('conversations', ['tenant_id' => $tenant->id, 'type' => 'direct']);
        $this->assertDatabaseHas('messages', ['user_id' => $member->id, 'body' => 'Private direct message']);
    }

    private function user(string $email): User
    {
        return User::create([
            'name' => 'Community User',
            'display_name' => 'Community User',
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
