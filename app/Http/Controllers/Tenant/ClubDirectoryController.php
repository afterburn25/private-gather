<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ClubDirectoryProfile;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ClubDirectoryController extends Controller
{
    public function edit(TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        abort_unless($tenant->type === Tenant::TYPE_CLUB, 404);

        $profile = ClubDirectoryProfile::firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['is_listed' => false, 'listing_name' => $tenant->name, 'club_type' => 'club', 'country_code' => 'US']
        );

        return view('tenant.manage.club-directory', compact('tenant', 'profile'));
    }

    public function update(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless($tenant->type === Tenant::TYPE_CLUB, 404);

        $data = $request->validate([
            'is_listed' => 'nullable|boolean', 'listing_name' => 'required|string|max:160',
            'club_type' => 'required|in:club,on_premise,off_premise,resort,party_organizer',
            'short_description' => 'nullable|string|max:2500', 'city' => 'nullable|string|max:120',
            'region' => 'nullable|string|max:120', 'country_code' => 'required|string|size:2',
            'postal_code' => 'nullable|string|max:24', 'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180', 'contact_url' => 'nullable|url:https|max:500',
            'amenities' => 'nullable|string|max:3000',
        ]);

        $amenities = collect(preg_split('/\r\n|\r|\n|,/', (string) ($data['amenities'] ?? '')))
            ->map(fn ($value) => trim($value))->filter()->unique()->take(40)->values()->all();

        ClubDirectoryProfile::updateOrCreate(['tenant_id' => $tenant->id], [
            'is_listed' => $request->boolean('is_listed'),
            'listing_name' => trim($data['listing_name']), 'club_type' => $data['club_type'],
            'short_description' => trim((string) ($data['short_description'] ?? '')) ?: null,
            'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'region' => trim((string) ($data['region'] ?? '')) ?: null,
            'country_code' => strtoupper($data['country_code']),
            'postal_code' => trim((string) ($data['postal_code'] ?? '')) ?: null,
            'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
            'contact_url' => $data['contact_url'] ?? null, 'amenities' => $amenities,
        ]);

        return back()->with('status', $request->boolean('is_listed')
            ? 'Club directory listing updated and enabled.' : 'Club directory listing updated and hidden.');
    }
}
