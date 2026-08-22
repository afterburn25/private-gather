<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AffiliateOffer;
use Illuminate\Support\Collection;

final class AffiliateAds
{
    public function forPlacement(
        string $placement,
        ?string $countryCode = null,
        ?string $region = null,
        ?string $city = null,
        int $limit = 3
    ): Collection {
        $countryCode = $countryCode ? strtoupper(trim($countryCode)) : null;
        $region = $region ? trim($region) : null;
        $city = $city ? trim($city) : null;

        return AffiliateOffer::query()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->whereJsonContains('placements', $placement)
            ->where(function ($query) use ($countryCode): void {
                $query->whereNull('country_code');
                if ($countryCode) {
                    $query->orWhere('country_code', $countryCode);
                }
            })
            ->where(function ($query) use ($region): void {
                $query->whereNull('region');
                if ($region) {
                    $query->orWhere('region', $region);
                }
            })
            ->where(function ($query) use ($city): void {
                $query->whereNull('city');
                if ($city) {
                    $query->orWhere('city', $city);
                }
            })
            ->orderByDesc('priority')
            ->orderBy('id')
            ->limit(max(1, min(12, $limit)))
            ->get();
    }
}
