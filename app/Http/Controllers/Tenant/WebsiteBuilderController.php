<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CmsFormSubmission;
use App\Models\CmsPage;
use App\Models\CmsReusableBlock;
use App\Models\CmsSection;
use App\Models\CommunityGroup;
use App\Models\Event;
use App\Models\SiteSetting;
use App\Support\Audit;
use App\Support\MountUrl;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class WebsiteBuilderController extends Controller
{
    private const SECTION_TYPES = [
        'hero' => 'Hero',
        'rich_text' => 'Rich text',
        'image_text' => 'Image + text',
        'event_grid' => 'Upcoming events',
        'chapter_grid' => 'Chapter directory',
        'membership_grid' => 'Membership levels',
        'gallery' => 'Gallery',
        'cta' => 'Call to action',
        'faq' => 'FAQ',
        'testimonials' => 'Testimonials',
        'stats' => 'Stats',
        'contact' => 'Contact form',
        'spacer' => 'Spacer',
    ];

    public function index(TenantContext $context): View
    {
        $tenant = $context->requireTenant();

        return view('tenant.manage.builder.index', [
            'tenant' => $tenant,
            'pages' => CmsPage::query()->where('tenant_id', $tenant->id)->withCount('sections')->orderByDesc('is_homepage')->orderBy('title')->get(),
            'reusableBlocks' => CmsReusableBlock::query()->where('tenant_id', $tenant->id)->where('status', 'active')->orderBy('name')->get(),
            'settings' => SiteSetting::query()->where('tenant_id', $tenant->id)->pluck('value', 'key')->all(),
            'newSubmissionCount' => CmsFormSubmission::query()->where('tenant_id', $tenant->id)->where('status', 'new')->count(),
            'templates' => $this->templates(),
        ]);
    }

    public function edit(TenantContext $context, CmsPage $page): View
    {
        $tenant = $context->requireTenant();
        $this->assertPage($page, $tenant->id);
        $page->load('sections');

        return view('tenant.manage.builder.edit', [
            'tenant' => $tenant,
            'page' => $page,
            'sectionTypes' => $this->allowedSectionTypes($tenant->isOrganization()),
            'reusableBlocks' => CmsReusableBlock::query()->where('tenant_id', $tenant->id)->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function updatePage(Request $request, TenantContext $context, CmsPage $page): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertPage($page, $tenant->id);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'seo_title' => ['nullable', 'string', 'max:190'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'social_title' => ['nullable', 'string', 'max:190'],
            'social_description' => ['nullable', 'string', 'max:500'],
            'social_image_url' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:draft,published'],
        ]);
        $data['social_image_url'] = $this->safeOptionalUrl($data['social_image_url'] ?? null, 'social_image_url');
        $before = $page->only(array_keys($data));
        $data['published_at'] = $data['status'] === 'published' ? ($page->published_at ?: now()) : null;
        $page->update($data);
        Audit::write('tenant.builder.page_updated', $page, $before, $page->only(array_keys($data)), $tenant->id, $request);

        return back()->with('status', 'Page settings saved.');
    }

    public function storeSection(Request $request, TenantContext $context, CmsPage $page): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertPage($page, $tenant->id);
        $allowed = array_keys($this->allowedSectionTypes($tenant->isOrganization()));
        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', $allowed)],
            'name' => ['nullable', 'string', 'max:120'],
        ]);
        $section = CmsSection::create([
            'cms_page_id' => $page->id,
            'type' => $data['type'],
            'name' => trim((string) ($data['name'] ?? '')) ?: self::SECTION_TYPES[$data['type']],
            'content' => $this->defaultContent($data['type']),
            'settings' => [],
            'sort_order' => ((int) $page->sections()->max('sort_order')) + 10,
            'is_enabled' => true,
        ]);
        Audit::write('tenant.builder.section_created', $section, null, ['type' => $section->type, 'page_id' => $page->id], $tenant->id, $request);

        return back()->with('status', 'Section added.');
    }

    public function updateSection(Request $request, TenantContext $context, CmsSection $section): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $section->loadMissing('page');
        $this->assertSection($section, $tenant->id);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'eyebrow' => ['nullable', 'string', 'max:100'],
            'heading' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'button_label' => ['nullable', 'string', 'max:100'],
            'button_url' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'items' => ['nullable', 'string', 'max:20000'],
            'layout' => ['nullable', 'in:default,centered,split,cards,grid'],
            'background' => ['nullable', 'in:default,muted,accent'],
            'columns' => ['nullable', 'integer', 'between:1,4'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $content = is_array($section->content) ? $section->content : [];
        foreach (['eyebrow', 'heading', 'body', 'button_label', 'image_alt'] as $key) {
            $content[$key] = trim((string) ($data[$key] ?? '')) ?: null;
        }
        $content['button_url'] = $this->safeOptionalUrl($data['button_url'] ?? null, 'button_url');
        $content['image_url'] = $this->safeOptionalUrl($data['image_url'] ?? null, 'image_url');
        if (array_key_exists('items', $data)) {
            $content['items'] = $this->parseItems($section->type, (string) ($data['items'] ?? ''));
        }

        $settings = is_array($section->settings) ? $section->settings : [];
        $settings['layout'] = $data['layout'] ?? 'default';
        $settings['background'] = $data['background'] ?? 'default';
        $settings['columns'] = (int) ($data['columns'] ?? 3);

        $before = ['name' => $section->name, 'content' => $section->content, 'settings' => $section->settings, 'is_enabled' => $section->is_enabled];
        $section->update([
            'name' => trim((string) ($data['name'] ?? '')) ?: null,
            'content' => $content,
            'settings' => $settings,
            'is_enabled' => $request->boolean('is_enabled'),
        ]);
        Audit::write('tenant.builder.section_updated', $section, $before, ['name' => $section->name, 'content' => $content, 'settings' => $settings, 'is_enabled' => $section->is_enabled], $tenant->id, $request);

        return back()->with('status', 'Section saved.');
    }

    public function reorder(Request $request, TenantContext $context, CmsPage $page): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertPage($page, $tenant->id);
        $data = $request->validate(['section_ids' => ['required', 'array', 'min:1'], 'section_ids.*' => ['required', 'integer', 'distinct']]);
        $posted = collect($data['section_ids'])->map(fn ($id) => (int) $id)->values();
        $existing = $page->sections()->pluck('id')->map(fn ($id) => (int) $id)->values();
        if ($posted->sort()->values()->all() !== $existing->sort()->values()->all()) {
            throw ValidationException::withMessages(['section_ids' => 'The section order must contain every section from this page exactly once.']);
        }

        DB::transaction(function () use ($page, $posted): void {
            CmsSection::query()->where('cms_page_id', $page->id)->lockForUpdate()->get();
            foreach ($posted as $index => $id) {
                CmsSection::query()->where('cms_page_id', $page->id)->whereKey($id)->update(['sort_order' => $index * 10, 'updated_at' => now()]);
            }
        });
        Audit::write('tenant.builder.sections_reordered', $page, null, ['section_ids' => $posted->all()], $tenant->id, $request);

        return back()->with('status', 'Section order saved.');
    }

    public function saveReusable(Request $request, TenantContext $context, CmsSection $section): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $section->loadMissing('page');
        $this->assertSection($section, $tenant->id);
        $name = trim($request->validate(['name' => ['required', 'string', 'max:120']])['name']);
        $block = CmsReusableBlock::create([
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
            'name' => $name,
            'type' => $section->type,
            'content' => $section->content,
            'settings' => $section->settings,
            'status' => 'active',
        ]);
        Audit::write('tenant.builder.reusable_block_created', $block, null, ['source_section_id' => $section->id], $tenant->id, $request);

        return back()->with('status', 'Reusable block saved.');
    }

    public function insertReusable(Request $request, TenantContext $context, CmsPage $page, CmsReusableBlock $block): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertPage($page, $tenant->id);
        abort_unless((int) $block->tenant_id === (int) $tenant->id && $block->status === 'active', 404);
        $section = CmsSection::create([
            'cms_page_id' => $page->id,
            'type' => $block->type,
            'name' => $block->name,
            'content' => $block->content,
            'settings' => $block->settings,
            'sort_order' => ((int) $page->sections()->max('sort_order')) + 10,
            'is_enabled' => true,
        ]);
        Audit::write('tenant.builder.reusable_block_inserted', $section, null, ['block_id' => $block->id], $tenant->id, $request);

        return back()->with('status', 'Reusable block inserted.');
    }

    public function archiveReusable(Request $request, TenantContext $context, CmsReusableBlock $block): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless((int) $block->tenant_id === (int) $tenant->id, 404);
        $block->update(['status' => 'archived']);
        Audit::write('tenant.builder.reusable_block_archived', $block, null, ['status' => 'archived'], $tenant->id, $request);

        return back()->with('status', 'Reusable block archived. Existing page copies are unchanged.');
    }

    public function preview(Request $request, TenantContext $context, CmsPage $page): View
    {
        $tenant = $context->requireTenant()->load('branding');
        $this->assertPage($page, $tenant->id);
        $page->load(['sections' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order')]);
        $events = Event::query()->where('tenant_id', $tenant->id)->where('status', 'published')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(12)->get();
        $chapters = $tenant->isOrganization()
            ? CommunityGroup::query()->where('tenant_id', $tenant->id)->where('group_type', CommunityGroup::TYPE_CHAPTER)->where('status', 'active')->orderBy('name')->get()
            : collect();
        $device = in_array($request->query('device'), ['desktop', 'mobile'], true) ? (string) $request->query('device') : 'desktop';

        return view('tenant.manage.builder.preview', compact('tenant', 'page', 'events', 'chapters', 'device'));
    }

    public function submissions(TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        return view('tenant.manage.builder.submissions', [
            'tenant' => $tenant,
            'submissions' => CmsFormSubmission::query()->where('tenant_id', $tenant->id)->with(['page:id,title', 'section:id,name'])->latest('submitted_at')->paginate(50),
        ]);
    }

    public function updateSubmission(Request $request, TenantContext $context, CmsFormSubmission $submission): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless((int) $submission->tenant_id === (int) $tenant->id, 404);
        $status = $request->validate(['status' => ['required', 'in:new,reviewed,closed']])['status'];
        $submission->update(['status' => $status]);
        Audit::write('tenant.builder.form_submission_updated', $submission, null, ['status' => $status], $tenant->id, $request);
        return back()->with('status', 'Submission status updated.');
    }

    private function assertPage(CmsPage $page, int $tenantId): void
    {
        abort_unless((int) $page->tenant_id === $tenantId, 404);
    }

    private function assertSection(CmsSection $section, int $tenantId): void
    {
        abort_unless($section->page && (int) $section->page->tenant_id === $tenantId, 404);
    }

    private function allowedSectionTypes(bool $organization): array
    {
        $types = self::SECTION_TYPES;
        if (! $organization) unset($types['chapter_grid']);
        return $types;
    }

    private function safeOptionalUrl(?string $value, string $field): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (MountUrl::to($value) === '') {
            throw ValidationException::withMessages([$field => 'Use a safe relative, http(s), mailto or telephone URL.']);
        }
        return $value;
    }

    private function parseItems(string $type, string $raw): array
    {
        $lines = collect(preg_split('/\R/u', trim($raw)) ?: [])->map(fn ($line) => trim((string) $line))->filter()->take(50);
        return $lines->map(function (string $line) use ($type): array {
            $parts = array_map('trim', explode('|', $line, 3));
            return match ($type) {
                'faq' => ['question' => $parts[0] ?? '', 'answer' => $parts[1] ?? ''],
                'testimonials' => ['quote' => $parts[0] ?? '', 'name' => $parts[1] ?? '', 'detail' => $parts[2] ?? ''],
                'stats' => ['value' => $parts[0] ?? '', 'label' => $parts[1] ?? ''],
                'gallery' => [
                    'url' => $this->safeOptionalUrl($parts[0] ?? null, 'items') ?: '',
                    'alt' => $parts[1] ?? '',
                    'caption' => $parts[2] ?? '',
                ],
                default => ['text' => $line],
            };
        })->filter(fn (array $item) => match ($type) {
            'faq' => $item['question'] !== '' && $item['answer'] !== '',
            'testimonials' => $item['quote'] !== '',
            'stats' => $item['value'] !== '' && $item['label'] !== '',
            'gallery' => $item['url'] !== '',
            default => true,
        })->values()->all();
    }

    private function defaultContent(string $type): array
    {
        return match ($type) {
            'hero' => ['eyebrow' => 'WELCOME', 'heading' => 'Your headline', 'body' => 'Introduce your community.', 'button_label' => 'Explore events', 'button_url' => '/events'],
            'event_grid' => ['heading' => 'Upcoming events', 'limit' => 6],
            'chapter_grid' => ['heading' => 'Our chapters'],
            'membership_grid' => ['heading' => 'Membership options'],
            'faq' => ['heading' => 'Frequently asked questions', 'items' => []],
            'testimonials' => ['heading' => 'What members say', 'items' => []],
            'stats' => ['heading' => 'At a glance', 'items' => []],
            'gallery' => ['heading' => 'Gallery', 'items' => []],
            'contact' => ['heading' => 'Contact us', 'body' => 'Send a private message to the organization.', 'button_label' => 'Send message'],
            'cta' => ['heading' => 'Ready to connect?', 'button_label' => 'Apply to join', 'button_url' => '/membership/apply'],
            default => ['heading' => self::SECTION_TYPES[$type] ?? 'Section', 'body' => ''],
        };
    }

    private function templates(): array
    {
        return [
            'immersive' => ['label' => 'Immersive Nightlife', 'summary' => 'Large hero, events, culture story and membership CTA.'],
            'editorial' => ['label' => 'Editorial Club', 'summary' => 'Refined story-first composition with calendar emphasis.'],
            'resort' => ['label' => 'Private Resort', 'summary' => 'Destination-style presentation built around experiences and membership.'],
        ];
    }
}
