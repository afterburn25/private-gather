<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Support\Audit;
use App\Support\Edition;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

final class PrivacyRequestController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        $query = DataRequest::query()->with('user');

        if (Edition::isSelfHosted()) {
            $tenant = $context->requireTenant();
            $query->whereHas('user.tenants', fn ($tenantQuery) => $tenantQuery->whereKey($tenant->id));
        }

        if ($status = trim((string) $request->query('status'))) {
            abort_unless(in_array($status, ['pending', 'processing', 'completed', 'rejected'], true), 422);
            $query->where('status', $status);
        }

        if ($type = trim((string) $request->query('type'))) {
            abort_unless(in_array($type, ['export', 'delete'], true), 422);
            $query->where('type', $type);
        }

        return view('admin.privacy-requests.index', [
            'requests' => $query->latest('requested_at')->paginate(100)->withQueryString(),
        ]);
    }

    public function update(Request $request, DataRequest $privacyRequest, TenantContext $context)
    {
        $privacyRequest->loadMissing('user');
        abort_unless($privacyRequest->user, 404);

        if (Edition::isSelfHosted()) {
            $tenant = $context->requireTenant();
            abort_unless($privacyRequest->user->tenants()->whereKey($tenant->id)->exists(), 404);
        }

        $data = $request->validate([
            'status' => 'required|in:pending,processing,completed,rejected',
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $before = [
            'status' => $privacyRequest->status,
            'completed_at' => $privacyRequest->completed_at?->toIso8601String(),
            'admin_notes' => $privacyRequest->admin_notes,
        ];

        $terminal = in_array($data['status'], ['completed', 'rejected'], true);
        $privacyRequest->update([
            'status' => $data['status'],
            'admin_notes' => trim((string) ($data['admin_notes'] ?? '')) ?: null,
            'completed_at' => $terminal ? ($privacyRequest->completed_at ?? now()) : null,
        ]);

        Audit::write(
            'privacy.request.updated',
            $privacyRequest,
            before: $before,
            after: [
                'status' => $privacyRequest->status,
                'completed_at' => $privacyRequest->completed_at?->toIso8601String(),
                'admin_notes' => $privacyRequest->admin_notes,
                'request_user_id' => $privacyRequest->user_id,
                'request_type' => $privacyRequest->type,
            ],
            tenantId: Edition::isSelfHosted() ? $context->id() : null,
            request: $request,
        );

        return back()->with('status', 'Privacy request updated.');
    }
}
