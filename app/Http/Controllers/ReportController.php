<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Message;
use App\Models\Report;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function store(Request $request, TenantContext $context)
    {
        $data = $request->validate([
            'reportable_type' => 'required|in:user,event,tenant,message',
            'reportable_id' => 'required|integer|min:1',
            'category' => 'required|in:safety,harassment,spam,fraud,privacy,inappropriate,other',
            'details' => 'nullable|string|max:5000',
        ]);

        $reporter = $request->user();
        $reportableId = (int) $data['reportable_id'];
        $currentTenantId = $context->check() ? $context->id() : null;
        $tenantId = $currentTenantId;

        switch ($data['reportable_type']) {
            case 'user':
                User::query()->findOrFail($reportableId);
                if ($currentTenantId !== null) {
                    abort_unless(
                        DB::table('tenant_users')
                            ->where('tenant_id', $currentTenantId)
                            ->where('user_id', $reportableId)
                            ->exists(),
                        404
                    );
                }
                break;

            case 'event':
                $event = Event::query()->findOrFail($reportableId);
                if ($currentTenantId !== null) {
                    abort_unless((int) $event->tenant_id === (int) $currentTenantId, 404);
                }
                $tenantId = $event->tenant_id;
                break;

            case 'tenant':
                $tenant = Tenant::query()->findOrFail($reportableId);
                if ($currentTenantId !== null) {
                    abort_unless((int) $tenant->id === (int) $currentTenantId, 404);
                }
                $tenantId = $tenant->id;
                break;

            case 'message':
                $message = Message::query()->findOrFail($reportableId);
                $conversation = DB::table('conversations')->where('id', $message->conversation_id)->first();
                abort_unless($conversation, 404);
                if ($currentTenantId !== null) {
                    abort_unless((int) $conversation->tenant_id === (int) $currentTenantId, 404);
                }
                abort_unless(
                    DB::table('conversation_participants')
                        ->where('conversation_id', $message->conversation_id)
                        ->where('user_id', $reporter->id)
                        ->exists(),
                    403,
                    'You can only report messages from conversations you participate in.'
                );
                $tenantId = $conversation->tenant_id ?: $tenantId;
                break;
        }

        Report::create([
            'reporter_id' => $reporter->id,
            'tenant_id' => $tenantId,
            ...$data,
            'reportable_id' => $reportableId,
        ]);

        return back()->with('status', 'Report submitted for review.');
    }
}
