<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Support\Edition;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

class ModerationController extends Controller
{
    public function index(TenantContext $context)
    {
        $query = Report::with('reporter');

        if (Edition::isSelfHosted()) {
            $query->where('tenant_id', $context->requireTenant()->id);
        }

        return view('admin.moderation.index', [
            'reports' => $query->latest()->paginate(100),
        ]);
    }

    public function update(Request $request, Report $report, TenantContext $context)
    {
        if (Edition::isSelfHosted()) {
            abort_unless($report->tenant_id === $context->requireTenant()->id, 404);
        }

        $data = $request->validate([
            'status' => 'required|in:open,reviewing,resolved,dismissed',
        ]);

        $report->update([
            'status' => $data['status'],
            'assigned_to' => $request->user()->id,
        ]);

        return back()->with('status', 'Moderation case updated.');
    }
}
