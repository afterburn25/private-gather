<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CmsFormSubmission;
use App\Models\CmsSection;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CmsFormController extends Controller
{
    public function store(Request $request, TenantContext $context, CmsSection $section): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $section->loadMissing('page');
        abort_unless(
            $section->page
            && (int) $section->page->tenant_id === (int) $tenant->id
            && $section->page->status === 'published'
            && $section->is_enabled
            && $section->type === 'contact',
            404
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'privacy_ack' => ['accepted'],
        ]);

        CmsFormSubmission::create([
            'tenant_id' => $tenant->id,
            'cms_page_id' => $section->page->id,
            'cms_section_id' => $section->id,
            'user_id' => $request->user()?->id,
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'message' => trim($data['message']),
            'privacy_ack' => true,
            'status' => 'new',
            'submitted_at' => now(),
        ]);

        return back()->with('status', 'Your message was sent to '.$tenant->name.'.');
    }
}
