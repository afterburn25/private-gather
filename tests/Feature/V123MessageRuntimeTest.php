<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class V123MessageRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'https://platform.test',
            'edition.name' => 'hosted',
            'platform.root_domain' => 'platform.test',
            'platform.central_domains' => ['platform.test', 'www.platform.test'],
            'platform.tenant_scheme' => 'https',
            'platform.tenant_mount_path' => '',
            'demo.enabled' => false,
        ]);
    }

    public function test_participant_can_set_and_clear_typing_state_but_nonparticipant_cannot(): void
    {
        [$tenant, $one, $two, $outsider, $conversation] = $this->conversationFixture();

        $this->actingAs($one)
            ->postJson('https://chat.platform.test/messages/'.$conversation->id.'/typing', ['typing' => true])
            ->assertOk()
            ->assertJson(['typing' => true]);

        $this->assertNotNull(DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $one->id)
            ->value('typing_at'));

        $this->actingAs($two)
            ->getJson('https://chat.platform.test/messages/'.$conversation->id.'/poll?after=0')
            ->assertOk()
            ->assertJsonPath('typing.0.id', $one->id);

        $this->actingAs($one)
            ->postJson('https://chat.platform.test/messages/'.$conversation->id.'/typing', ['typing' => false])
            ->assertOk()
            ->assertJson(['typing' => false]);

        $this->assertNull(DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $one->id)
            ->value('typing_at'));

        $this->actingAs($outsider)
            ->postJson('https://chat.platform.test/messages/'.$conversation->id.'/typing', ['typing' => true])
            ->assertNotFound();
    }

    public function test_attachment_is_private_to_conversation_participants(): void
    {
        Storage::fake('local');
        [$tenant, $one, $two, $outsider, $conversation] = $this->conversationFixture();
        $path = 'tenant/'.$tenant->id.'/community/'.$one->id.'/messages/private-note.txt';
        Storage::disk('local')->put($path, 'private attachment');

        $messageId = DB::table('messages')->insertGetId([
            'conversation_id' => $conversation->id,
            'user_id' => $one->id,
            'body' => 'Attached privately',
            'attachment_path' => $path,
            'attachment_type' => 'file',
            'attachment_mime' => 'text/plain',
            'attachment_size' => strlen('private attachment'),
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $message = Message::query()->findOrFail($messageId);

        $response = $this->actingAs($two)
            ->get('https://chat.platform.test/message-media/'.$message->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);

        $this->actingAs($outsider)
            ->get('https://chat.platform.test/message-media/'.$message->id)
            ->assertNotFound();
    }

    public function test_deleted_or_missing_attachment_is_not_served(): void
    {
        Storage::fake('local');
        [$tenant, $one, $two, , $conversation] = $this->conversationFixture();

        $deletedId = DB::table('messages')->insertGetId([
            'conversation_id' => $conversation->id,
            'user_id' => $one->id,
            'body' => 'Deleted attachment',
            'attachment_path' => 'tenant/'.$tenant->id.'/missing/deleted.txt',
            'attachment_type' => 'file',
            'attachment_mime' => 'text/plain',
            'attachment_size' => 10,
            'status' => 'sent',
            'deleted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($two)
            ->get('https://chat.platform.test/message-media/'.$deletedId)
            ->assertNotFound();
    }

    /** @return array{0:Tenant,1:User,2:User,3:User,4:Conversation} */
    private function conversationFixture(): array
    {
        $tenant = Tenant::create([
            'name' => 'Message Runtime Club',
            'slug' => 'message-runtime-club',
            'type' => Tenant::TYPE_CLUB,
            'status' => 'active',
            'plan' => 'starter',
            'settings' => [],
        ]);
        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'chat.platform.test',
            'type' => TenantDomain::TYPE_PLATFORM_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => 'managed',
            'dns_status' => 'active',
            'dns_last_checked_at' => now(),
            'redirect_to_primary' => false,
        ]);

        $one = $this->user('message-one');
        $two = $this->user('message-two');
        $outsider = $this->user('message-outsider');
        foreach ([$one, $two, $outsider] as $user) {
            $tenant->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);
        }

        $conversation = Conversation::create([
            'tenant_id' => $tenant->id,
            'type' => 'direct',
            'subject' => null,
        ]);
        $conversation->participants()->attach($one->id, ['last_read_at' => null]);
        $conversation->participants()->attach($two->id, ['last_read_at' => null]);

        return [$tenant, $one, $two, $outsider, $conversation];
    }

    private function user(string $username): User
    {
        return User::create([
            'name' => $username,
            'username' => $username,
            'display_name' => $username,
            'email' => $username.'@example.test',
            'password' => 'Password12345',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'status' => 'active',
            'is_platform_admin' => false,
            'adult_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'privacy_version' => '1.0',
        ]);
    }
}
