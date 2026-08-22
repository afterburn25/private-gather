@php
    $slot = $adSlot ?? 'leaderboard';
    $offer = $adOffer ?? null;
@endphp
<section class="lux-ad-leaderboard-wrap" aria-label="Sponsored advertising">
    <div class="container">
        @if($offer)
            <a class="lux-ad-leaderboard has-offer" rel="sponsored nofollow noopener" href="{{ route('affiliate.go',['offer'=>$offer->id,'placement'=>$slot]) }}">
                <span class="lux-ad-disclosure">SPONSORED</span>
                <span class="lux-ad-copy"><strong>{{ $offer->title }}</strong><small>{{ $offer->advertiser_name }} · {{ $offer->description }}</small></span>
                <span class="lux-ad-cta">{{ $offer->cta_label }} →</span>
            </a>
        @else
            <div class="lux-ad-leaderboard is-placeholder" data-ad-slot="{{ $slot }}">
                <span class="lux-ad-disclosure">SPONSORED</span>
                <span class="lux-ad-copy"><strong>Premium leaderboard placement</strong><small>Advertising space available on Private Gather</small></span>
                <span class="lux-ad-size">970 × 90</span>
            </div>
        @endif
    </div>
</section>
