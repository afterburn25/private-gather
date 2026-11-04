<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantMembershipLevel;
use App\Models\TenantMembershipTerm;
use App\Models\User;
use App\Services\MembershipLevelService;
use App\Services\TenantMembershipAccessService;
use App\Support\Audit;
use App\Support\CsvCell;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MemberOperationsController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        $query = $tenant->users()
            ->with('profile')
            ->wherePivot('role', 'member');

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($users) use ($q): void {
                $users->where('users.email', 'like', '%'.$q.'%')
                    ->orWhere('users.display_name', 'like', '%'.$q.'%')
                    ->orWhere('users.name', 'like', '%'.$q.'%');
            });
        }
        if ($status = trim((string) $request->query('status'))) {
            if (in_array($status, ['active', 'suspended', 'pending'], true)) {
                $query->wherePivot('status', $status);
            }
        }

        $members = $query->orderByRaw('COALESCE(users.display_name, users.name)')->paginate(75)->withQueryString();
        $memberIds = $members->getCollection()->pluck('id');

        return view('tenant.manage.member-operations', [
            'tenant' => $tenant,
            'members' => $members,
            'levels' => TenantMembershipLevel::query()->where('tenant_id', $tenant->id)->where('is_active', true)->orderByDesc('is_default')->orderBy('sort_order')->get(),
            'terms' => TenantMembershipTerm::query()->where('tenant_id', $tenant->id)->whereIn('user_id', $memberIds)->with('level')->get()->keyBy('user_id'),
        ]);
    }

    public function bulk(
        Request $request,
        TenantContext $context,
        MembershipLevelService $memberships,
    ): RedirectResponse {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'action' => ['required', 'in:suspend,reactivate,renew,assign_level'],
            'user_ids' => ['required', 'array', 'min:1', 'max:500'],
            'user_ids.*' => ['integer', 'distinct'],
            'membership_level_id' => ['nullable', 'integer'],
            'auto_renew' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['user_ids'])));
        $members = $tenant->users()
            ->wherePivot('role', 'member')
            ->whereIn('users.id', $ids)
            ->get()
            ->keyBy('id');
        if ($members->count() !== count($ids)) {
            abort(404, 'One or more selected members do not belong to this organization.');
        }

        $level = null;
        if ($data['action'] === 'assign_level') {
            if (empty($data['membership_level_id'])) {
                throw ValidationException::withMessages(['membership_level_id' => 'Choose a membership level for the bulk assignment.']);
            }
            $level = TenantMembershipLevel::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->findOrFail((int) $data['membership_level_id']);
        }

        $processed = 0;
        $skipped = [];
        foreach ($ids as $id) {
            /** @var User $user */
            $user = $members->get($id);
            try {
                match ($data['action']) {
                    'suspend' => $memberships->suspend($tenant, $user, $request->user(), $data['reason'] ?? null),
                    'reactivate' => $memberships->reactivate($tenant, $user, $request->user()),
                    'renew' => $this->renewMember($tenant->id, $user, $memberships, $request->user()),
                    'assign_level' => $memberships->applyLevel($tenant, $user, $level, $request->user(), 'bulk_staff', $request->boolean('auto_renew'), $data['reason'] ?? null),
                };
                $processed++;
            } catch (ValidationException $exception) {
                $skipped[] = ($user->display_name ?: $user->email).': '.collect($exception->errors())->flatten()->first();
            }
        }

        Audit::write('tenant.membership.bulk_operation', $tenant, after: [
            'action' => $data['action'],
            'requested' => count($ids),
            'processed' => $processed,
            'skipped' => count($skipped),
            'membership_level_id' => $level?->id,
        ], tenantId: $tenant->id, request: $request);

        $message = ucfirst(str_replace('_', ' ', $data['action'])).' processed for '.$processed.' member(s).';
        if ($skipped !== []) {
            $message .= ' '.count($skipped).' skipped: '.implode(' | ', array_slice($skipped, 0, 5));
        }
        return back()->with('status', $message);
    }

    public function import(
        Request $request,
        TenantContext $context,
        MembershipLevelService $memberships,
        TenantMembershipAccessService $access,
    ): RedirectResponse {
        $tenant = $context->requireTenant();
        $request->validate(['csv' => ['required', 'file', 'max:2048']]);
        $path = $request->file('csv')?->getRealPath();
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['csv' => 'The uploaded CSV could not be read.']);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['csv' => 'The uploaded CSV could not be opened.']);
        }

        try {
            $headers = fgetcsv($handle);
            if (! is_array($headers)) {
                throw ValidationException::withMessages(['csv' => 'The CSV must contain a header row.']);
            }
            $headers = array_map(static fn ($value) => strtolower(trim((string) $value)), $headers);
            foreach (['email', 'membership_level'] as $required) {
                if (! in_array($required, $headers, true)) {
                    throw ValidationException::withMessages(['csv' => 'Required CSV columns: email, membership_level. Optional: auto_renew, notes.']);
                }
            }

            $levelIndex = $this->levelIndex($tenant->id);
            $processed = 0;
            $skipped = [];
            $rowNumber = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($rowNumber > 1001) {
                    throw ValidationException::withMessages(['csv' => 'CSV import is limited to 1,000 member rows per upload.']);
                }
                if ($row === [null] || count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) continue;
                $row = array_pad($row, count($headers), null);
                $record = array_combine($headers, array_slice($row, 0, count($headers)));
                if (! is_array($record)) continue;

                $email = strtolower(trim((string) ($record['email'] ?? '')));
                $levelKey = strtolower(trim((string) ($record['membership_level'] ?? '')));
                $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? User::query()->whereRaw('LOWER(email) = ?', [$email])->first() : null;
                $level = $levelIndex[$levelKey] ?? null;
                if (! $user || ! $level) {
                    $skipped[] = 'Row '.$rowNumber.': account or membership level not found.';
                    continue;
                }

                try {
                    $access->assertEligible($tenant, $user);
                    $memberships->applyLevel(
                        $tenant,
                        $user,
                        $level,
                        $request->user(),
                        'csv_import',
                        $this->csvBoolean($record['auto_renew'] ?? null),
                        trim((string) ($record['notes'] ?? '')) ?: null,
                    );
                    $processed++;
                } catch (ValidationException $exception) {
                    $skipped[] = 'Row '.$rowNumber.': '.collect($exception->errors())->flatten()->first();
                }
            }
        } finally {
            fclose($handle);
        }

        Audit::write('tenant.membership.csv_import', $tenant, after: [
            'processed' => $processed,
            'skipped' => count($skipped),
        ], tenantId: $tenant->id, request: $request);

        $message = 'CSV membership import activated/updated '.$processed.' member(s).';
        if ($skipped !== []) $message .= ' '.count($skipped).' skipped: '.implode(' | ', array_slice($skipped, 0, 5));
        return back()->with('status', $message);
    }

    public function export(Request $request, TenantContext $context): StreamedResponse
    {
        $tenant = $context->requireTenant();
        $filename = 'private-gather-members-'.$tenant->slug.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($tenant): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['display_name', 'email', 'organization_status', 'membership_level', 'term_status', 'period_ends_at', 'access_expires_at', 'auto_renew']);

            $tenant->users()->wherePivot('role', 'member')->orderBy('users.id')->chunk(250, function ($users) use ($tenant, $out): void {
                $terms = TenantMembershipTerm::query()->where('tenant_id', $tenant->id)->whereIn('user_id', $users->pluck('id'))->with('level')->get()->keyBy('user_id');
                foreach ($users as $user) {
                    $term = $terms->get($user->id);
                    fputcsv($out, [
                        CsvCell::safe($user->display_name ?: $user->name),
                        CsvCell::safe($user->email),
                        CsvCell::safe((string) $user->pivot->status),
                        CsvCell::safe($term?->level?->name ?? 'Legacy / unstructured'),
                        CsvCell::safe($term?->status ?? 'legacy'),
                        $term?->current_period_ends_at?->toIso8601String(),
                        $term?->expires_at?->toIso8601String(),
                        $term?->auto_renew ? 'yes' : 'no',
                    ]);
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function renewMember(int $tenantId, User $user, MembershipLevelService $memberships, ?User $actor): TenantMembershipTerm
    {
        $term = TenantMembershipTerm::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)->first();
        if (! $term) {
            throw ValidationException::withMessages(['membership' => 'This legacy member does not yet have a structured term to renew.']);
        }
        return $memberships->renew($term, $actor);
    }

    private function levelIndex(int $tenantId): array
    {
        $index = [];
        foreach (TenantMembershipLevel::query()->where('tenant_id', $tenantId)->where('is_active', true)->get() as $level) {
            $index[strtolower($level->slug)] = $level;
            $index[strtolower($level->name)] = $level;
        }
        return $index;
    }

    private function csvBoolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'on'], true);
    }
}
