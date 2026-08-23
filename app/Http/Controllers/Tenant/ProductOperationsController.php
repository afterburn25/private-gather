<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\CmsSection;
use App\Models\Order;
use App\Models\TenantBranding;
use App\Models\TenantMembershipLevel;
use App\Models\TenantMembershipTerm;
use App\Models\User;
use App\Services\MembershipLevelService;
use App\Services\ProductCompletionService;
use App\Support\ThemeCatalog;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class ProductOperationsController extends Controller
{
    public function onboarding(Request $request, TenantContext $context): View
    {
        $tenant = $context->requireTenant()->load('branding');
        return view('tenant.manage.product-operations', [
            'section' => 'onboarding',
            'tenant' => $tenant,
            'themes' => ThemeCatalog::tenantPresets(),
            'progress' => DB::table('onboarding_progress')->where('user_id', $request->user()->id)->first(),
        ]);
    }

    public function saveOnboarding(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'marketplace_summary' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'theme' => ['nullable', 'string', 'max:80'],
        ]);
        $settings = $tenant->settings ?? [];
        $settings['marketplace_summary'] = trim((string) ($data['marketplace_summary'] ?? ''));
        $settings['city'] = trim((string) ($data['city'] ?? ''));
        $settings['region'] = trim((string) ($data['region'] ?? ''));
        $settings['marketplace_enabled'] = $request->boolean('marketplace_enabled');
        $tenant->update(['settings' => $settings]);

        if (! empty($data['theme']) && array_key_exists($data['theme'], ThemeCatalog::tenantPresets())) {
            TenantBranding::updateOrCreate(['tenant_id' => $tenant->id], ['theme' => ['preset' => $data['theme']]]);
        }
        DB::table('onboarding_progress')->updateOrInsert(
            ['user_id' => $request->user()->id],
            ['tenant_id' => $tenant->id, 'tenant_step' => 6, 'tenant_completed_at' => now(), 'updated_at' => now(), 'created_at' => now()]
        );
        return redirect()->route('tenant.dashboard')->with('status', 'Club setup completed.');
    }

    public function crm(Request $request, TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        $members = $tenant->users()->with('profile')->wherePivot('status', 'active');
        if ($q = trim((string) $request->query('q'))) {
            $members->where(fn ($query) => $query->where('users.display_name', 'like', '%'.$q.'%')->orWhere('users.email', 'like', '%'.$q.'%'));
        }
        if ($segment = trim((string) $request->query('segment'))) {
            if ($segment === 'vip') {
                $vipTagIds = DB::table('tenant_member_tags')->where('tenant_id', $tenant->id)->where('name', 'like', '%VIP%')->pluck('id');
                $members->whereIn('users.id', DB::table('tenant_member_tag_assignments')->whereIn('tag_id', $vipTagIds)->pluck('user_id'));
            } elseif (in_array($segment, ['active', 'expired', 'grace', 'cancelled'], true)) {
                $members->whereIn('users.id', DB::table('tenant_membership_terms')->where('tenant_id', $tenant->id)->where('status', $segment)->pluck('user_id'));
            }
        }
        $members = $members->paginate(50)->withQueryString();
        $memberIds = $members->pluck('id');
        return view('tenant.manage.product-operations', [
            'section' => 'crm', 'tenant' => $tenant, 'members' => $members,
            'tags' => DB::table('tenant_member_tags')->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'assignments' => DB::table('tenant_member_tag_assignments')->where('tenant_id', $tenant->id)->whereIn('user_id', $memberIds)->get()->groupBy('user_id'),
            'notes' => DB::table('tenant_member_notes')->where('tenant_id', $tenant->id)->whereIn('user_id', $memberIds)->latest()->get()->groupBy('user_id'),
            'terms' => DB::table('tenant_membership_terms')->where('tenant_id', $tenant->id)->whereIn('user_id', $memberIds)->get()->keyBy('user_id'),
            'spend' => DB::table('orders')->where('tenant_id', $tenant->id)->whereIn('user_id', $memberIds)->whereIn('status', ['paid', 'completed'])->select('user_id', DB::raw('SUM(total_cents) as total'))->groupBy('user_id')->pluck('total', 'user_id'),
            'attendance' => DB::table('event_checkins')->join('events', 'events.id', '=', 'event_checkins.event_id')->where('events.tenant_id', $tenant->id)->whereIn('event_checkins.user_id', $memberIds)->select('event_checkins.user_id', DB::raw('COUNT(*) as total'))->groupBy('event_checkins.user_id')->pluck('total', 'event_checkins.user_id'),
        ]);
    }

    public function createTag(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/']]);
        DB::table('tenant_member_tags')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'name' => trim($data['name'])],
            ['color' => $data['color'] ?? '#d9bc78', 'created_at' => now(), 'updated_at' => now()]
        );
        return back()->with('status', 'CRM tag saved.');
    }

    public function assignTag(Request $request, TenantContext $context, User $user): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertMember($tenant->id, $user->id);
        $tag = (int) $request->validate(['tag_id' => ['required', 'integer']])['tag_id'];
        abort_unless(DB::table('tenant_member_tags')->where('tenant_id', $tenant->id)->where('id', $tag)->exists(), 404);
        DB::table('tenant_member_tag_assignments')->updateOrInsert(
            ['tag_id' => $tag, 'user_id' => $user->id],
            ['tenant_id' => $tenant->id, 'created_at' => now(), 'updated_at' => now()]
        );
        return back()->with('status', 'Member tagged.');
    }

    public function addNote(Request $request, TenantContext $context, User $user): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertMember($tenant->id, $user->id);
        $body = trim($request->validate(['body' => ['required', 'string', 'max:4000']])['body']);
        DB::table('tenant_member_notes')->insert([
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'author_id' => $request->user()->id,
            'body' => $body, 'is_private' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return back()->with('status', 'Private CRM note added.');
    }

    public function commerce(TenantContext $context, ProductCompletionService $products): View
    {
        $tenant = $context->requireTenant();
        Order::where('tenant_id', $tenant->id)->whereIn('status', ['paid', 'completed'])->with(['payments', 'tenant'])
            ->chunkById(100, fn ($orders) => $orders->each(fn ($order) => $products->recordPaidOrder($order)));
        return view('tenant.manage.product-operations', [
            'section' => 'commerce', 'tenant' => $tenant,
            'merchant' => DB::table('merchant_accounts')->where('tenant_id', $tenant->id)->first(),
            'ledger' => DB::table('marketplace_ledger_entries')->where('tenant_id', $tenant->id)->latest()->paginate(40),
            'payouts' => DB::table('payouts')->where('tenant_id', $tenant->id)->latest()->limit(20)->get(),
            'refunds' => DB::table('refunds')->where('tenant_id', $tenant->id)->latest()->limit(20)->get(),
            'available' => $products->availablePayoutCents($tenant->id),
            'subscriptions' => DB::table('membership_subscriptions')->where('tenant_id', $tenant->id)->latest()->limit(50)->get(),
            'levels' => TenantMembershipLevel::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('sort_order')->get(),
            'members' => $tenant->users()->wherePivot('status', 'active')->orderByRaw('COALESCE(display_name, name)')->get(['users.id', 'users.name', 'users.display_name']),
        ]);
    }

    public function merchant(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:60'],
            'provider_account_ref' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:not_connected,pending,connected,restricted'],
        ]);
        DB::table('merchant_accounts')->updateOrInsert(
            ['tenant_id' => $tenant->id],
            $data + ['capabilities' => json_encode(['ticket_sales' => true, 'membership_billing' => true, 'split_ledger' => true]), 'onboarded_at' => $data['status'] === 'connected' ? now() : null, 'created_at' => now(), 'updated_at' => now()]
        );
        return back()->with('status', 'Merchant account settings updated.');
    }

    public function payout(Request $request, TenantContext $context, ProductCompletionService $products): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $amount = (int) $request->validate(['amount_cents' => ['required', 'integer', 'min:100']])['amount_cents'];
        abort_if($amount > $products->availablePayoutCents($tenant->id), 422, 'Requested payout exceeds the available balance.');
        DB::table('payouts')->insert(['tenant_id' => $tenant->id, 'amount_cents' => $amount, 'currency' => 'USD', 'status' => 'requested', 'scheduled_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('status', 'Payout request created.');
    }

    public function refund(Request $request, TenantContext $context, Order $order, PaymentGateway $gateway): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless((int) $order->tenant_id === (int) $tenant->id && in_array($order->status, ['paid', 'completed'], true), 404);
        $data = $request->validate(['amount_cents' => ['required', 'integer', 'min:1'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $amount = (int) $data['amount_cents'];
        $already = (int) DB::table('refunds')->where('order_id', $order->id)->whereIn('status', ['processing', 'completed'])->sum('amount_cents');
        abort_if($already + $amount > (int) $order->total_cents, 422, 'Refund amount exceeds the refundable order balance.');
        $result = $gateway->refund($order, $amount);
        DB::table('refunds')->insert([
            'tenant_id' => $tenant->id, 'order_id' => $order->id,
            'payment_id' => $order->payments()->where('status', 'paid')->value('id'), 'amount_cents' => $amount,
            'status' => ($result['status'] ?? 'manual_required') === 'refunded' ? 'completed' : (($result['status'] ?? '') === 'processing' ? 'processing' : 'manual_required'),
            'provider_reference' => $result['reference'] ?? null, 'reason' => $data['reason'] ?? null,
            'metadata' => json_encode($result), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return back()->with('status', 'Refund request recorded.');
    }

    public function createSubscription(Request $request, TenantContext $context, MembershipLevelService $memberships): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'membership_level_id' => ['required', 'integer'],
            'complimentary' => ['nullable', 'boolean'],
        ]);
        $user = User::findOrFail((int) $data['user_id']);
        $this->assertMember($tenant->id, $user->id);
        $level = TenantMembershipLevel::where('tenant_id', $tenant->id)->whereKey((int) $data['membership_level_id'])->where('is_active', true)->firstOrFail();
        $complimentary = $request->boolean('complimentary');
        $term = $memberships->applyLevel($tenant, $user, $level, $request->user(), $complimentary ? 'complimentary' : 'subscription', ! $complimentary && $level->billing_interval !== TenantMembershipLevel::INTERVAL_NONE);
        $merchant = DB::table('merchant_accounts')->where('tenant_id', $tenant->id)->first();
        $requiresProvider = ! $complimentary && $level->price_cents > 0;
        $status = $requiresProvider ? (($merchant?->status ?? null) === 'connected' ? 'pending_provider' : 'awaiting_provider') : 'active';
        DB::table('membership_subscriptions')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'user_id' => $user->id],
            [
                'membership_level_id' => $level->id, 'membership_term_id' => $term->id,
                'provider' => $merchant?->provider ?? 'manual', 'provider_reference' => null,
                'status' => $status, 'billing_interval' => $level->billing_interval,
                'amount_cents' => $complimentary ? 0 : $level->price_cents, 'currency' => $level->currency,
                'current_period_starts_at' => $term->current_period_starts_at,
                'current_period_ends_at' => $term->current_period_ends_at,
                'cancel_at_period_end' => false, 'created_at' => now(), 'updated_at' => now(),
            ]
        );
        $message = $status === 'active'
            ? 'Membership subscription/term activated.'
            : 'Membership term prepared. External recurring billing remains pending until the configured provider confirms it.';
        return back()->with('status', $message);
    }

    public function cancelSubscription(Request $request, TenantContext $context, int $id, MembershipLevelService $memberships): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $subscription = DB::table('membership_subscriptions')->where('tenant_id', $tenant->id)->where('id', $id)->first();
        abort_unless($subscription, 404);
        $atEnd = ! $request->boolean('immediate');
        if ($subscription->membership_term_id) {
            $term = TenantMembershipTerm::where('tenant_id', $tenant->id)->find($subscription->membership_term_id);
            if ($term) $memberships->cancel($term, $request->user(), $atEnd, 'Cancelled from Club OS subscription management.');
        }
        DB::table('membership_subscriptions')->where('id', $id)->update([
            'status' => $atEnd ? 'active' : 'cancelled', 'cancel_at_period_end' => $atEnd, 'updated_at' => now(),
        ]);
        return back()->with('status', $atEnd ? 'Subscription will cancel at period end.' : 'Subscription cancelled immediately.');
    }

    public function renewSubscription(Request $request, TenantContext $context, int $id, MembershipLevelService $memberships): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $subscription = DB::table('membership_subscriptions')->where('tenant_id', $tenant->id)->where('id', $id)->first();
        abort_unless($subscription && $subscription->membership_term_id, 404);
        abort_if(in_array($subscription->status, ['awaiting_provider', 'pending_provider'], true), 422, 'The billing provider must confirm this subscription before renewal.');
        $term = TenantMembershipTerm::where('tenant_id', $tenant->id)->findOrFail($subscription->membership_term_id);
        $term = $memberships->renew($term, $request->user());
        DB::table('membership_subscriptions')->where('id', $id)->update([
            'status' => 'active', 'current_period_starts_at' => $term->current_period_starts_at,
            'current_period_ends_at' => $term->current_period_ends_at, 'cancel_at_period_end' => false, 'updated_at' => now(),
        ]);
        return back()->with('status', 'Membership period renewed.');
    }

    public function domains(TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        return view('tenant.manage.product-operations', ['section' => 'domains', 'tenant' => $tenant, 'orders' => DB::table('domain_orders')->where('tenant_id', $tenant->id)->latest()->get()]);
    }

    public function domainSearch(Request $request, ProductCompletionService $products): JsonResponse
    {
        return response()->json($products->domainAvailability((string) $request->query('domain')))->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function domainRegister(Request $request, TenantContext $context, ProductCompletionService $products): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $domain = strtolower(trim($request->validate(['domain' => ['required', 'string', 'max:253']])['domain']));
        $result = $products->requestDomainRegistration($tenant, $domain);
        return back()->with('status', $result['status'] === 'available' ? 'Domain registration request created.' : 'Domain request saved; connect a registrar provider to complete registration.');
    }

    public function builder(TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        return view('tenant.manage.product-operations', [
            'section' => 'builder', 'tenant' => $tenant,
            'pages' => CmsPage::where('tenant_id', $tenant->id)->with('sections')->orderByDesc('is_homepage')->get(),
            'templates' => $this->templates(),
        ]);
    }

    public function applyTemplate(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $key = $request->validate(['template' => ['required', 'string', 'max:80']])['template'];
        $template = $this->templates()[$key] ?? null;
        abort_unless($template, 404);
        DB::transaction(function () use ($tenant, $template): void {
            $page = CmsPage::firstOrCreate(
                ['tenant_id' => $tenant->id, 'is_homepage' => true],
                ['slug' => 'home', 'title' => 'Home', 'status' => 'published', 'published_at' => now()]
            );
            $page->sections()->delete();
            foreach ($template['sections'] as $i => $section) {
                CmsSection::create(['cms_page_id' => $page->id, 'type' => $section['type'], 'name' => $section['name'], 'content' => $section['content'], 'settings' => [], 'sort_order' => $i * 10, 'is_enabled' => true]);
            }
        });
        return back()->with('status', 'Homepage template applied. You can refine every section in the CMS.');
    }

    public function growth(TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        return view('tenant.manage.product-operations', [
            'section' => 'growth', 'tenant' => $tenant,
            'referrals' => DB::table('referral_codes')->where('tenant_id', $tenant->id)->latest()->get(),
            'contacts' => DB::table('marketing_contacts')->where('tenant_id', $tenant->id)->latest()->limit(100)->get(),
            'campaigns' => DB::table('marketing_campaigns')->where('tenant_id', $tenant->id)->latest()->limit(50)->get(),
        ]);
    }

    public function referral(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate(['code' => ['nullable', 'alpha_dash', 'max:64'], 'commission_bps' => ['nullable', 'integer', 'between:0,5000']]);
        $code = strtoupper($data['code'] ?? Str::random(10));
        abort_if(DB::table('referral_codes')->where('code', $code)->exists(), 422, 'That referral code is already in use.');
        DB::table('referral_codes')->insert(['tenant_id' => $tenant->id, 'user_id' => null, 'code' => $code, 'type' => 'promoter', 'commission_bps' => $data['commission_bps'] ?? 0, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('status', 'Referral code created.');
    }

    public function contact(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'], 'email_consent' => ['nullable', 'boolean'], 'sms_consent' => ['nullable', 'boolean'],
        ]);
        abort_if(empty($data['email']) && empty($data['phone']), 422, 'Email or phone is required.');
        DB::table('marketing_contacts')->insert([
            'tenant_id' => $tenant->id, 'name' => $data['name'] ?? null, 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
            'email_opt_in_at' => $request->boolean('email_consent') ? now() : null,
            'sms_opt_in_at' => $request->boolean('sms_consent') ? now() : null,
            'source' => 'manual', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return back()->with('status', 'Contact saved. Marketing consent remains off unless explicitly selected.');
    }

    public function campaign(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'channel' => ['required', 'in:email,sms'],
            'subject' => ['nullable', 'string', 'max:255'], 'body' => ['required', 'string', 'max:20000'],
            'scheduled_at' => ['nullable', 'date'],
        ]);
        $consentField = $data['channel'] === 'email' ? 'email_opt_in_at' : 'sms_opt_in_at';
        $eligible = DB::table('marketing_contacts')->where('tenant_id', $tenant->id)->where('status', 'active')->whereNotNull($consentField)->count();
        DB::table('marketing_campaigns')->insert([
            'tenant_id' => $tenant->id, 'created_by' => $request->user()->id,
            'name' => $data['name'], 'channel' => $data['channel'],
            'status' => ! empty($data['scheduled_at']) ? 'scheduled' : 'draft',
            'audience' => json_encode(['consent_required' => true, 'eligible_contacts' => $eligible]),
            'subject' => $data['subject'] ?? null, 'body' => $data['body'], 'scheduled_at' => $data['scheduled_at'] ?? null,
            'stats' => json_encode(['eligible' => $eligible, 'sent' => 0]), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return back()->with('status', 'Campaign saved for '.$eligible.' explicitly opted-in contact(s). No non-consented contacts were added.');
    }

    public function respondReview(Request $request, TenantContext $context, int $id): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $body = trim($request->validate(['response' => ['required', 'string', 'max:4000']])['response']);
        $updated = DB::table('reviews')->where('id', $id)->where('tenant_id', $tenant->id)->update(['tenant_response' => $body, 'tenant_responded_at' => now(), 'updated_at' => now()]);
        abort_unless($updated, 404);
        return back()->with('status', 'Public response saved.');
    }

    private function assertMember(int $tenantId, int $userId): void
    {
        abort_unless(DB::table('tenant_users')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 'active')->exists(), 404);
    }

    private function templates(): array
    {
        return [
            'immersive' => ['label' => 'Immersive Nightlife', 'sections' => [
                ['type' => 'hero', 'name' => 'Hero', 'content' => ['eyebrow' => 'WELCOME', 'heading' => 'A private night, designed around your community.', 'body' => 'Membership, events and connection in one discreet home.', 'primary_label' => 'Explore events', 'primary_url' => '/events']],
                ['type' => 'event_grid', 'name' => 'Upcoming Events', 'content' => ['heading' => 'Upcoming experiences', 'limit' => 6]],
                ['type' => 'image_text', 'name' => 'About', 'content' => ['heading' => 'A community with standards', 'body' => 'Use this section to explain your culture, membership process and what guests can expect.']],
                ['type' => 'cta', 'name' => 'Join', 'content' => ['heading' => 'Ready to become part of the community?', 'body' => 'Review membership expectations and submit your application.', 'button_label' => 'Apply to join', 'button_url' => '/membership/apply']],
            ]],
            'editorial' => ['label' => 'Editorial Club', 'sections' => [
                ['type' => 'hero', 'name' => 'Cover Story', 'content' => ['eyebrow' => 'THE CLUB', 'heading' => 'Where the right people meet.', 'body' => 'A refined private community for memorable nights.', 'primary_label' => 'Membership', 'primary_url' => '/membership/apply']],
                ['type' => 'image_text', 'name' => 'Our Story', 'content' => ['heading' => 'More than a venue', 'body' => 'Tell your story with editorial photography and a strong point of view.']],
                ['type' => 'event_grid', 'name' => 'Calendar', 'content' => ['heading' => 'The calendar', 'limit' => 8]],
            ]],
            'resort' => ['label' => 'Private Resort', 'sections' => [
                ['type' => 'hero', 'name' => 'Destination Hero', 'content' => ['eyebrow' => 'ESCAPE', 'heading' => 'Arrive. Unwind. Belong.', 'body' => 'A destination-style private community experience.', 'primary_label' => 'Plan your visit', 'primary_url' => '/events']],
                ['type' => 'event_grid', 'name' => 'Experiences', 'content' => ['heading' => 'Upcoming escapes', 'limit' => 6]],
                ['type' => 'cta', 'name' => 'Membership', 'content' => ['heading' => 'Make it your private getaway', 'body' => 'Membership unlocks the complete community experience.', 'button_label' => 'Apply', 'button_url' => '/membership/apply']],
            ]],
        ];
    }
}
