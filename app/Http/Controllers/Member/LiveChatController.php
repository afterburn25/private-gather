<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CommunityChatMessage;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LiveChatController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        $messages = CommunityChatMessage::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->with('user:id,name,display_name')
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        return view('member.community.chat', [
            'tenant' => $tenant,
            'messages' => $messages,
            'canModerate' => $this->canModerate($request, $tenant->id),
        ]);
    }

    public function poll(Request $request, TenantContext $context): JsonResponse
    {
        $tenant = $context->requireTenant();
        $after = max(0, (int) $request->query('after', 0));

        $messages = CommunityChatMessage::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('id', '>', $after)
            ->with('user:id,name,display_name')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (CommunityChatMessage $message) => $this->serialize($message, (int) $request->user()->id));

        return response()->json([
            'messages' => $messages,
            'last_id' => (int) ($messages->last()['id'] ?? $after),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function store(Request $request, TenantContext $context): JsonResponse|RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);

        $message = CommunityChatMessage::create([
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
            'status' => 'active',
        ])->load('user:id,name,display_name');

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->serialize($message, (int) $request->user()->id)], 201);
        }

        return back();
    }

    public function destroy(Request $request, CommunityChatMessage $message, TenantContext $context): JsonResponse|RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless((int) $message->tenant_id === (int) $tenant->id, 404);
        abort_unless((int) $message->user_id === (int) $request->user()->id || $this->canModerate($request, $tenant->id), 403);
        $message->update(['status' => 'removed']);

        if ($request->expectsJson()) {
            return response()->json(['removed' => true]);
        }

        return back();
    }

    private function serialize(CommunityChatMessage $message, int $viewerId): array
    {
        return [
            'id' => (int) $message->id,
            'user_id' => (int) $message->user_id,
            'name' => $message->user?->display_name ?: $message->user?->name ?: 'Former member',
            'body' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
            'mine' => (int) $message->user_id === $viewerId,
        ];
    }

    private function canModerate(Request $request, int $tenantId): bool
    {
        $membership = $request->user()->tenants()->whereKey($tenantId)->first()?->pivot;

        return $membership && in_array($membership->role, ['owner', 'admin', 'manager'], true);
    }
}
