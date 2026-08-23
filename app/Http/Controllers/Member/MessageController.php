<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Models\Conversation;
use App\Models\Message;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MessageController
{
    public function index(Request $request, TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        $items = Conversation::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('participants', fn ($query) => $query->where('users.id', $request->user()->id))
            ->with([
                'participants:id,name,display_name',
                'messages' => fn ($query) => $query->latest('id')->limit(1),
            ])
            ->latest('updated_at')
            ->get();

        $members = $tenant->users()
            ->wherePivot('status', 'active')
            ->where('users.id', '!=', $request->user()->id)
            ->orderByRaw('COALESCE(display_name, name)')
            ->get(['users.id', 'users.name', 'users.display_name']);

        return view('member.messages.index', [
            'tenant' => $tenant,
            'conversations' => $items,
            'members' => $members,
        ]);
    }

    public function start(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'recipient_id' => ['nullable', 'integer', 'exists:users,id'],
            'recipient_ids' => ['nullable', 'array', 'max:24'],
            'recipient_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'subject' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $recipientIds = array_map('intval', (array) ($data['recipient_ids'] ?? []));
        if (! empty($data['recipient_id'])) {
            $recipientIds[] = (int) $data['recipient_id'];
        }
        $recipientIds = array_values(array_unique(array_filter($recipientIds)));
        abort_if($recipientIds === [] || in_array((int) $request->user()->id, $recipientIds, true), 422, 'Choose at least one other community member.');

        foreach ($recipientIds as $recipientId) {
            $this->assertSiteMember($tenant->id, $recipientId);
            $this->assertMessagingAllowed((int) $request->user()->id, $recipientId);
        }

        $conversation = DB::transaction(function () use ($request, $data, $tenant, $recipientIds): Conversation {
            $conversation = count($recipientIds) === 1
                ? $this->existingDirectConversation($tenant->id, (int) $request->user()->id, $recipientIds[0])
                : null;

            if (! $conversation) {
                $conversation = Conversation::create([
                    'tenant_id' => $tenant->id,
                    'type' => count($recipientIds) === 1 ? 'direct' : 'group',
                    'subject' => count($recipientIds) > 1 ? trim((string) ($data['subject'] ?? '')) ?: 'Group chat' : null,
                ]);
                $conversation->participants()->attach($request->user()->id, ['last_read_at' => now()]);
                foreach ($recipientIds as $recipientId) {
                    $conversation->participants()->attach($recipientId, ['last_read_at' => null]);
                }
            }

            $conversation->messages()->create([
                'user_id' => $request->user()->id,
                'body' => trim($data['body']),
                'status' => 'sent',
            ]);
            $conversation->touch();

            return $conversation;
        });

        return redirect()->route('messages.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation, TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        $this->assertConversationAccess($request, $conversation, $tenant->id);
        $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now(), 'typing_at' => null]);

        return view('member.messages.show', [
            'tenant' => $tenant,
            'conversation' => $conversation->load('participants:id,name,display_name'),
            'messages' => $conversation->messages()->with('user:id,name,display_name')->orderBy('id')->paginate(100),
        ]);
    }

    public function poll(Request $request, Conversation $conversation, TenantContext $context): JsonResponse
    {
        $tenant = $context->requireTenant();
        $this->assertConversationAccess($request, $conversation, $tenant->id);
        $after = max(0, (int) $request->query('after', 0));

        $messages = $conversation->messages()
            ->where('id', '>', $after)
            ->with('user:id,name,display_name')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (Message $message) => $this->serializeMessage($message, (int) $request->user()->id));

        if ($messages->isNotEmpty()) {
            $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);
        }

        $typingSince = now()->subSeconds(8);
        $typing = DB::table('conversation_participants')
            ->join('users', 'users.id', '=', 'conversation_participants.user_id')
            ->where('conversation_participants.conversation_id', $conversation->id)
            ->where('conversation_participants.user_id', '!=', $request->user()->id)
            ->whereNotNull('conversation_participants.typing_at')
            ->where('conversation_participants.typing_at', '>=', $typingSince)
            ->get(['users.id', 'users.name', 'users.display_name'])
            ->map(fn ($user) => [
                'id' => (int) $user->id,
                'name' => $user->display_name ?: $user->name,
            ])->values();

        return response()->json([
            'messages' => $messages,
            'last_id' => (int) ($messages->last()['id'] ?? $after),
            'typing' => $typing,
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function typing(Request $request, Conversation $conversation, TenantContext $context): JsonResponse
    {
        $tenant = $context->requireTenant();
        $this->assertConversationAccess($request, $conversation, $tenant->id);
        $data = $request->validate(['typing' => ['nullable', 'boolean']]);
        $typing = ! array_key_exists('typing', $data) || (bool) $data['typing'];

        $updated = DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $request->user()->id)
            ->update([
                'typing_at' => $typing ? now() : null,
                'updated_at' => now(),
            ]);
        abort_unless($updated === 1, 404);

        return response()->json(['typing' => $typing])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function attachment(Request $request, Message $message, TenantContext $context): StreamedResponse
    {
        $tenant = $context->requireTenant();
        $conversation = Conversation::query()->findOrFail((int) $message->conversation_id);
        $this->assertConversationAccess($request, $conversation, $tenant->id);
        abort_if($message->deleted_at !== null, 404);

        $path = trim((string) $message->attachment_path);
        abort_unless($path !== '' && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => trim((string) $message->attachment_mime) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function store(Request $request, Conversation $conversation, TenantContext $context): JsonResponse|RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertConversationAccess($request, $conversation, $tenant->id);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);

        $otherIds = $conversation->participants()
            ->where('users.id', '!=', $request->user()->id)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_if($otherIds === [], 422, 'This conversation no longer has recipients.');
        foreach ($otherIds as $otherId) {
            $this->assertSiteMember($tenant->id, $otherId);
            $this->assertMessagingAllowed((int) $request->user()->id, $otherId);
        }

        $message = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
            'status' => 'sent',
        ])->load('user:id,name,display_name');
        $conversation->touch();
        $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now(), 'typing_at' => null]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->serializeMessage($message, (int) $request->user()->id)], 201);
        }

        return back();
    }

    public function mute(Request $request, Conversation $conversation, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertConversationAccess($request, $conversation, $tenant->id);
        $current = (bool) $conversation->participants()->where('users.id', $request->user()->id)->first()?->pivot?->muted;
        $conversation->participants()->updateExistingPivot($request->user()->id, ['muted' => ! $current]);

        return back();
    }

    private function assertConversationAccess(Request $request, Conversation $conversation, int $tenantId): void
    {
        abort_unless((int) $conversation->tenant_id === $tenantId, 404);
        abort_unless($conversation->participants()->where('users.id', $request->user()->id)->exists(), 404);
    }

    private function assertSiteMember(int $tenantId, int $userId): void
    {
        $member = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        abort_unless($member, 422, 'Messages can only be sent to active members of this Private Gather site.');
    }

    private function existingDirectConversation(int $tenantId, int $senderId, int $recipientId): ?Conversation
    {
        return Conversation::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'direct')
            ->whereHas('participants', fn ($query) => $query->where('users.id', $senderId))
            ->whereHas('participants', fn ($query) => $query->where('users.id', $recipientId))
            ->withCount('participants')
            ->get()
            ->first(fn (Conversation $conversation) => (int) $conversation->participants_count === 2);
    }

    private function serializeMessage(Message $message, int $viewerId): array
    {
        return [
            'id' => (int) $message->id,
            'user_id' => (int) $message->user_id,
            'name' => $message->user?->display_name ?: $message->user?->name ?: 'Former member',
            'body' => $message->body,
            'attachment' => $message->attachment_path ? [
                'type' => $message->attachment_type,
                'mime' => $message->attachment_mime,
                'size' => $message->attachment_size !== null ? (int) $message->attachment_size : null,
                'url' => route('messages.attachment', $message),
            ] : null,
            'created_at' => $message->created_at?->toIso8601String(),
            'mine' => (int) $message->user_id === $viewerId,
        ];
    }

    private function assertMessagingAllowed(int $senderId, int $recipientId): void
    {
        $blocked = DB::table('user_blocks')
            ->where(function ($query) use ($senderId, $recipientId): void {
                $query->where([
                    'user_id' => $senderId,
                    'blocked_user_id' => $recipientId,
                ])->orWhere(function ($reverse) use ($senderId, $recipientId): void {
                    $reverse->where([
                        'user_id' => $recipientId,
                        'blocked_user_id' => $senderId,
                    ]);
                });
            })
            ->exists();

        abort_if($blocked, 403, 'Messaging unavailable for this member.');
    }
}
