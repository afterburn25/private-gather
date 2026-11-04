<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CommunityGroup;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ChapterController extends Controller
{
    public function index(TenantContext $context): View
    {
        $tenant = $this->organization($context);

        return view('tenant.manage.chapters', [
            'tenant' => $tenant,
            'chapters' => CommunityGroup::query()
                ->where('tenant_id', $tenant->id)
                ->where('group_type', CommunityGroup::TYPE_CHAPTER)
                ->with('chapterLead:id,name,display_name,email')
                ->withCount(['members as active_members_count' => fn ($query) => $query->where('community_group_members.status', 'active')])
                ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderBy('region')
                ->orderBy('city')
                ->orderBy('name')
                ->get(),
            'leadCandidates' => $tenant->users()
                ->wherePivot('status', 'active')
                ->orderBy('display_name')
                ->get(['users.id', 'users.name', 'users.display_name', 'users.email']),
        ]);
    }

    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $this->organization($context);
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $tenant, $data): void {
            $leadId = $this->validatedLead($tenant->id, $data['chapter_lead_user_id'] ?? null);
            $group = CommunityGroup::create([
                'tenant_id' => $tenant->id,
                'created_by' => $request->user()->id,
                'name' => trim($data['name']),
                'slug' => $this->uniqueSlug($tenant->id, $data['name']),
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'group_type' => CommunityGroup::TYPE_CHAPTER,
                'city' => trim((string) ($data['city'] ?? '')) ?: null,
                'region' => trim((string) ($data['region'] ?? '')) ?: null,
                'operator_managed' => true,
                'chapter_lead_user_id' => $leadId,
                'visibility' => $data['visibility'],
                'join_policy' => $data['join_policy'],
                'status' => 'active',
                'allow_member_posts' => $request->boolean('allow_member_posts'),
            ]);

            $group->members()->syncWithoutDetaching([
                $request->user()->id => ['role' => 'moderator', 'status' => 'active'],
            ]);

            if ($leadId) {
                $group->members()->syncWithoutDetaching([
                    $leadId => ['role' => 'moderator', 'status' => 'active'],
                ]);
                DB::table('community_group_members')
                    ->where('community_group_id', $group->id)
                    ->where('user_id', $leadId)
                    ->update(['role' => 'moderator', 'status' => 'active', 'updated_at' => now()]);
            }

            $this->audit($request, $tenant->id, 'chapter.created', $group->id, [
                'name' => $group->name,
                'city' => $group->city,
                'region' => $group->region,
                'chapter_lead_user_id' => $leadId,
                'visibility' => $group->visibility,
                'join_policy' => $group->join_policy,
            ]);
        });

        return redirect()->route('tenant.chapters.index')->with('status', 'Chapter created.');
    }

    public function update(Request $request, TenantContext $context, int $chapter): RedirectResponse
    {
        $tenant = $this->organization($context);
        $group = $this->chapter($tenant->id, $chapter);
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $tenant, $group, $data): void {
            $before = [
                'name' => $group->name,
                'city' => $group->city,
                'region' => $group->region,
                'chapter_lead_user_id' => $group->chapter_lead_user_id,
                'visibility' => $group->visibility,
                'join_policy' => $group->join_policy,
                'allow_member_posts' => $group->allow_member_posts,
            ];
            $leadId = $this->validatedLead($tenant->id, $data['chapter_lead_user_id'] ?? null);

            $group->update([
                'name' => trim($data['name']),
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'city' => trim((string) ($data['city'] ?? '')) ?: null,
                'region' => trim((string) ($data['region'] ?? '')) ?: null,
                'chapter_lead_user_id' => $leadId,
                'visibility' => $data['visibility'],
                'join_policy' => $data['join_policy'],
                'allow_member_posts' => $request->boolean('allow_member_posts'),
                'operator_managed' => true,
            ]);

            if ($leadId) {
                $group->members()->syncWithoutDetaching([
                    $leadId => ['role' => 'moderator', 'status' => 'active'],
                ]);
                DB::table('community_group_members')
                    ->where('community_group_id', $group->id)
                    ->where('user_id', $leadId)
                    ->update(['role' => 'moderator', 'status' => 'active', 'updated_at' => now()]);
            }

            $this->audit($request, $tenant->id, 'chapter.updated', $group->id, [
                'before' => $before,
                'after' => [
                    'name' => $group->name,
                    'city' => $group->city,
                    'region' => $group->region,
                    'chapter_lead_user_id' => $leadId,
                    'visibility' => $group->visibility,
                    'join_policy' => $group->join_policy,
                    'allow_member_posts' => $group->allow_member_posts,
                ],
            ]);
        });

        return back()->with('status', 'Chapter updated.');
    }

    public function archive(Request $request, TenantContext $context, int $chapter): RedirectResponse
    {
        $tenant = $this->organization($context);
        $group = $this->chapter($tenant->id, $chapter);

        $group->update(['status' => 'archived']);
        $this->audit($request, $tenant->id, 'chapter.archived', $group->id, ['name' => $group->name]);

        return back()->with('status', 'Chapter archived.');
    }

    public function restore(Request $request, TenantContext $context, int $chapter): RedirectResponse
    {
        $tenant = $this->organization($context);
        $group = $this->chapter($tenant->id, $chapter, false);

        $group->update(['status' => 'active']);
        $this->audit($request, $tenant->id, 'chapter.restored', $group->id, ['name' => $group->name]);

        return back()->with('status', 'Chapter restored.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:4000'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'chapter_lead_user_id' => ['nullable', 'integer'],
            // Public exposes only chapter identity/location on the branded site;
            // chapter discussions still require authenticated tenant membership.
            'visibility' => ['required', 'in:public,tenant,invite_only'],
            'join_policy' => ['required', 'in:open,approval,invite_only'],
            'allow_member_posts' => ['nullable', 'boolean'],
        ]);
    }

    private function validatedLead(int $tenantId, mixed $userId): ?int
    {
        if (! $userId) {
            return null;
        }

        $id = (int) $userId;
        $valid = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $id)
            ->where('status', 'active')
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'chapter_lead_user_id' => 'Choose an active member of this organization as chapter lead.',
            ]);
        }

        return $id;
    }

    private function uniqueSlug(int $tenantId, string $name): string
    {
        $base = Str::slug($name) ?: 'chapter';
        $slug = $base;
        $suffix = 2;

        while (CommunityGroup::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function organization(TenantContext $context): Tenant
    {
        $tenant = $context->requireTenant();
        abort_unless($tenant->isOrganization(), 404);

        return $tenant;
    }

    private function chapter(int $tenantId, int $chapter, bool $activeOnly = true): CommunityGroup
    {
        $query = CommunityGroup::query()
            ->where('tenant_id', $tenantId)
            ->where('group_type', CommunityGroup::TYPE_CHAPTER)
            ->whereKey($chapter);

        if ($activeOnly) {
            $query->where('status', 'active');
        }

        return $query->firstOrFail();
    }

    private function audit(Request $request, int $tenantId, string $action, int $subjectId, array $after): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => 'community_group',
            'subject_id' => $subjectId,
            'before' => null,
            'after' => json_encode($after, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }
}
