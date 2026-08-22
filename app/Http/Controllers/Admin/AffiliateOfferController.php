<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateConversion;
use App\Models\AffiliateOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AffiliateOfferController extends Controller
{
    public function index(): View
    {
        $offers = AffiliateOffer::query()->withCount('clicks')->withSum('conversions', 'commission_cents')->orderByDesc('priority')->orderBy('title')->get();
        $summary = [
            'clicks' => (int) \App\Models\AffiliateClick::count(),
            'conversions' => (int) AffiliateConversion::count(),
            'commission_cents' => (int) AffiliateConversion::whereIn('status', ['reported', 'approved', 'paid'])->sum('commission_cents'),
        ];
        return view('admin.affiliate-offers', compact('offers', 'summary'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $slug = Str::slug((string) ($data['slug'] ?: $data['title']));
        abort_if($slug === '' || AffiliateOffer::where('slug', $slug)->exists(), 422, 'Affiliate offer slug is invalid or already used.');
        AffiliateOffer::create($this->payload($request, $data, $slug));
        return back()->with('status', 'Affiliate/sponsored offer created.');
    }

    public function update(Request $request, AffiliateOffer $offer): RedirectResponse
    {
        $data = $this->validated($request);
        $slug = Str::slug((string) ($data['slug'] ?: $data['title']));
        abort_if($slug === '' || AffiliateOffer::where('slug', $slug)->whereKeyNot($offer->id)->exists(), 422, 'Affiliate offer slug is invalid or already used.');
        $offer->update($this->payload($request, $data, $slug));
        return back()->with('status', 'Affiliate/sponsored offer updated.');
    }

    public function conversion(Request $request, AffiliateOffer $offer): RedirectResponse
    {
        $data = $request->validate([
            'external_reference' => 'nullable|string|max:180', 'sale_amount' => 'nullable|numeric|min:0|max:10000000',
            'commission' => 'required|numeric|min:0|max:10000000', 'currency' => 'required|string|size:3',
            'status' => 'required|in:reported,approved,paid,reversed', 'occurred_at' => 'required|date',
        ]);
        AffiliateConversion::create([
            'affiliate_offer_id' => $offer->id, 'external_reference' => $data['external_reference'] ?? null,
            'sale_amount_cents' => (int) round(((float) ($data['sale_amount'] ?? 0)) * 100),
            'commission_cents' => (int) round(((float) $data['commission']) * 100),
            'currency' => strtoupper($data['currency']), 'status' => $data['status'], 'occurred_at' => $data['occurred_at'],
        ]);
        return back()->with('status', 'Affiliate conversion/commission recorded.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'slug' => 'nullable|string|max:120', 'title' => 'required|string|max:180', 'advertiser_name' => 'required|string|max:180',
            'relationship_type' => 'required|in:affiliate,sponsored,house', 'category' => 'required|in:cruise,resort,travel,event,product,service,other',
            'description' => 'nullable|string|max:5000', 'affiliate_url' => 'required|url:https|max:5000', 'image_url' => 'nullable|url:https|max:1000',
            'cta_label' => 'required|string|max:80', 'status' => 'required|in:draft,active,paused,ended', 'placements' => 'required|array|min:1|max:8',
            'placements.*' => 'in:home,club_directory,club_detail,events,community', 'country_code' => 'nullable|string|size:2', 'region' => 'nullable|string|max:120',
            'city' => 'nullable|string|max:120', 'priority' => 'required|integer|min:0|max:10000', 'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at', 'disclosure' => 'required|string|max:255',
        ]);
    }

    private function payload(Request $request, array $data, string $slug): array
    {
        return [
            'slug' => $slug, 'title' => trim($data['title']), 'advertiser_name' => trim($data['advertiser_name']),
            'relationship_type' => $data['relationship_type'], 'category' => $data['category'], 'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'affiliate_url' => trim($data['affiliate_url']), 'image_url' => trim((string) ($data['image_url'] ?? '')) ?: null,
            'cta_label' => trim($data['cta_label']), 'status' => $data['status'], 'placements' => array_values(array_unique($data['placements'])),
            'country_code' => ! empty($data['country_code']) ? strtoupper($data['country_code']) : null,
            'region' => trim((string) ($data['region'] ?? '')) ?: null, 'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'priority' => (int) $data['priority'], 'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null,
            'disclosure' => trim($data['disclosure']),
        ];
    }
}
